<?php

declare(strict_types=1);

namespace eSheep\Twigs;

use eSheep\Twigs\TokenParser\TagTokenParser;
use TalesFromADev\TailwindMerge\TailwindMerge;
use TalesFromADev\TailwindMerge\TailwindMergeInterface;
use Yiisoft\Arrays\ArrayHelper;
use Twig\Extra\Html\Cva;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Single entry point Twig extension that bundles functions, filters, and tags
 * from multiple packages.
 *
 * Usage:
 *   $twig->addExtension(new \eSheep\Twigs\Extension());
 */
class Extension extends AbstractExtension
{
    private ?TailwindMergeInterface $tailwindMerge;

    /** @var array{remove_duplicate_classes: bool, tailwind_merge: bool} */
    private array $mergeOptions;

    /**
     * @param array{remove_duplicate_classes?: bool, tailwind_merge?: bool} $mergeOptions
     *   Default options for the merge_attributes filter.
     */
    public function __construct(
        ?TailwindMergeInterface $tailwindMerge = null,
        array $mergeOptions = [],
    ) {
        $this->tailwindMerge = $tailwindMerge;
        $this->mergeOptions = [
            'remove_duplicate_classes' => $mergeOptions['remove_duplicate_classes'] ?? true,
            'tailwind_merge' => $mergeOptions['tailwind_merge'] ?? true,
        ];
    }

    public function getFunctions(): array
    {
        return [
            // html_cva / cva — from twig/html-extra
            new TwigFunction('html_cva', [self::class, 'cva']),
            new TwigFunction('cva', [self::class, 'cva']),

            // html_tag / tag — ported from Craft CMS
            new TwigFunction('html_tag', [self::class, 'tagFunction'], ['is_safe' => ['html']]),
            new TwigFunction('tag', [self::class, 'tagFunction'], ['is_safe' => ['html']]),

            // html_attr / attr — ported from Craft CMS
            new TwigFunction('html_attr', [Html::class, 'renderTagAttributes'], ['is_safe' => ['html']]),
            new TwigFunction('attr', [Html::class, 'renderTagAttributes'], ['is_safe' => ['html']]),

            // Spatie Ray
            new TwigFunction('ray', [self::class, 'ray']),
        ];
    }

    public function getFilters(): array
    {
        return [
            // html_attr / attr — ported from Craft CMS (modifies attributes on existing HTML tags)
            new TwigFilter('html_attr', [self::class, 'attrFilter'], ['is_safe' => ['html']]),
            new TwigFilter('attr', [self::class, 'attrFilter'], ['is_safe' => ['html']]),

            // merge_attributes — merge two attribute arrays with optional class dedup/tailwind merge
            new TwigFilter('merge_attributes', [$this, 'mergeAttributes']),

            // tailwind_merge / tw_merge — from tales-from-a-dev/tailwind-merge-php
            new TwigFilter('tailwind_merge', [$this, 'tailwindMerge'], ['is_variadic' => true]),
            new TwigFilter('tw_merge', [$this, 'tailwindMerge'], ['is_variadic' => true]),

            // Spatie Ray
            new TwigFilter('ray', [self::class, 'ray']),
        ];
    }

    public function getTokenParsers(): array
    {
        return [
            new TagTokenParser('tag'),
            new TagTokenParser('html_tag'),
        ];
    }

    /**
     * Creates a CVA (Class Variant Authority) instance.
     *
     * @param string|array $base Base classes always applied.
     * @param array $variants Variant category → variant name → classes mapping.
     * @param array $compoundVariants Rules for applying classes when multiple variants match.
     * @param array $defaultVariant Default variant selections.
     */
    public static function cva(
        string|array $base = [],
        array $variants = [],
        array $compoundVariants = [],
        array $defaultVariant = [],
    ): Cva {
        return new Cva($base, $variants, $compoundVariants, $defaultVariant);
    }

    /**
     * Generates an HTML tag.
     *
     * Supports `html` and `text` keys in attributes:
     * - `html`: raw HTML content for the tag body.
     * - `text`: text content (will be HTML-encoded) for the tag body.
     *
     * @param string $type The tag name (e.g., 'div', 'span').
     * @param array $attributes Tag attributes. May include `html` and `text` keys.
     */
    public static function tagFunction(string $type, array $attributes = []): string
    {
        $html = ArrayHelper::remove($attributes, 'html', '');
        $text = ArrayHelper::remove($attributes, 'text');

        if ($text !== null) {
            $html = Html::encode($text);
        }

        return Html::tag($type, $html, $attributes);
    }

    /**
     * Modifies the attributes of an existing HTML tag string.
     *
     * Returns the original string unchanged if it contains no valid HTML tag.
     *
     * @param string $tag The HTML tag string to modify.
     * @param array $attributes The attributes to merge.
     */
    public static function attrFilter(string $tag, array $attributes): string
    {
        try {
            return Html::modifyTagAttributes($tag, $attributes);
        } catch (\InvalidArgumentException) {
            trigger_error("Could not modify tag attributes: invalid HTML tag", E_USER_WARNING);
            return $tag;
        }
    }

    /**
     * Merges two attribute arrays, with optional class deduplication and Tailwind class merging.
     *
     * @param iterable $base The base attributes (left side of the filter).
     * @param iterable $attributes The attributes to merge in.
     * @param array{remove_duplicate_classes?: bool, tailwind_merge?: bool} $mergeOptions Override extension defaults.
     */
    public function mergeAttributes(
        iterable $base,
        iterable $attributes,
        array $mergeOptions = [],
    ): array {
        $removeDuplicateClasses = $mergeOptions['remove_duplicate_classes'] ?? $this->mergeOptions['remove_duplicate_classes'];
        $tailwindMerge = $mergeOptions['tailwind_merge'] ?? $this->mergeOptions['tailwind_merge'];

        $base = Html::normalizeTagAttributes(self::toArray($base));
        $attributes = Html::normalizeTagAttributes(self::toArray($attributes));
        $merged = ArrayHelper::merge($base, $attributes);

        if (isset($merged['class']) && is_array($merged['class'])) {
            if ($removeDuplicateClasses) {
                $merged['class'] = array_unique($merged['class']);
            }

            if ($tailwindMerge && !empty($merged['class'])) {
                $this->tailwindMerge ??= new TailwindMerge();
                $merged['class'] = Html::explodeClass(
                    $this->tailwindMerge->merge(...array_values($merged['class'])),
                );
            }
        }

        return $merged;
    }

    private static function toArray(iterable $value): array
    {
        return $value instanceof \Traversable ? iterator_to_array($value) : $value;
    }

    /**
     * Merges Tailwind CSS classes, resolving conflicts.
     *
     * @param string $value The base class string.
     * @param array $additionalClasses Additional classes to merge (variadic from Twig).
     */
    public function tailwindMerge(string $value, array $additionalClasses = []): string
    {
        $this->tailwindMerge ??= new TailwindMerge();

        return $this->tailwindMerge->merge($value, ...$additionalClasses);
    }

    public static function ray(mixed ...$args)
    {
        if (function_exists('ray')) {
            /** @phpstan-ignore function.notFound */
            \ray(...$args);
        } else {
            foreach ($args as $arg) {
                error_log(print_r($arg, true));
            }
        }
    }
}
