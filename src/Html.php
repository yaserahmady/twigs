<?php

declare(strict_types=1);

namespace eSheep\Twigs;

use InvalidArgumentException;
use Yiisoft\Arrays\ArrayHelper;
use Yiisoft\Html\Html as BaseHtml;

/**
 * HTML helper class providing tag building, attribute rendering, and HTML string manipulation.
 *
 * Ported from Craft CMS (craft\helpers\Html), adapted to use yiisoft/html instead of Yii2.
 *
 * @see PROVENANCE.md
 */
final class Html
{
    /**
     * @var array List of tag attributes that should be specially handled when their values are of array type.
     * In particular, if the value of the `data` attribute is `['name' => 'xyz', 'age' => 13]`, two attributes
     * will be generated instead of one: `data-name="xyz" data-age="13"`.
     * @since 4.0.0
     */
    public static array $dataAttributes = [
        'aria',
        'data',
        'data-hx',
        'data-ng',
        'hx',
        'ng',
    ];

    /**
     * @var string[]
     * @see sortedDataAttributes()
     */
    private static array $sortedDataAttributes;

    /**
     * @var string[] HTML void elements that cannot have content.
     */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * Generates a complete HTML tag.
     *
     * @param string $name The tag name.
     * @param string $content The tag content (raw HTML).
     * @param array $options The tag attributes in terms of name-value pairs.
     */
    public static function tag(string $name, string $content = '', array $options = []): string
    {
        $attrString = static::renderTagAttributes($options);

        if (in_array(strtolower($name), self::VOID_ELEMENTS, true)) {
            return '<' . $name . $attrString . '>';
        }

        return '<' . $name . $attrString . '>' . $content . '</' . $name . '>';
    }

    /**
     * Generates a begin tag.
     *
     * @param string $name The tag name.
     * @param array $options The tag attributes in terms of name-value pairs.
     */
    public static function beginTag(string $name, array $options = []): string
    {
        return '<' . $name . static::renderTagAttributes($options) . '>';
    }

    /**
     * Generates an end tag.
     *
     * @param string $name The tag name.
     */
    public static function endTag(string $name): string
    {
        return '</' . $name . '>';
    }

    /**
     * Renders the HTML tag attributes.
     *
     * Attributes whose values are of boolean type will be treated as
     * {@link https://www.w3.org/TR/html5/infrastructure.html#boolean-attributes boolean attributes}.
     *
     * Attributes whose values are null will not be rendered.
     *
     * @param array $attributes Attributes to be rendered. The attribute values will be HTML-encoded.
     * @return string The rendering result. If the attributes are not empty, they will be rendered
     * into a string with a leading white space. If there is no attribute, an empty string will be returned.
     */
    public static function renderTagAttributes(array $attributes): string
    {
        $attributes = static::normalizeTagAttributes($attributes);
        $attributes = self::expandDataAttributes($attributes);

        return BaseHtml::renderTagAttributes($attributes);
    }

    /**
     * Encodes special characters into HTML entities.
     */
    public static function encode(string $content, bool $doubleEncode = true): string
    {
        return BaseHtml::encode($content, $doubleEncode);
    }

    /**
     * Decodes HTML entities back to their corresponding characters.
     */
    public static function decode(string $content): string
    {
        return htmlspecialchars_decode($content, ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Modifies a HTML tag's attributes, supporting the same attribute definitions as {@see renderTagAttributes()}.
     *
     * @param string $tag The HTML tag whose attributes should be modified.
     * @param array $attributes The attributes to be added to the tag.
     * @return string The modified HTML tag.
     * @throws InvalidArgumentException if `$tag` doesn't contain a valid HTML tag
     * @since 3.3.0
     */
    public static function modifyTagAttributes(string $tag, array $attributes): string
    {
        // Normalize the attributes & merge with the old attributes
        $attributes = static::normalizeTagAttributes($attributes);
        $oldAttributes = static::parseTagAttributes($tag, 0, $start, $end, true);
        $attributes = ArrayHelper::merge($oldAttributes, $attributes);

        // Ensure we don't have any duplicate classes
        if (isset($attributes['class']) && is_array($attributes['class'])) {
            $attributes['class'] = array_unique($attributes['class']);
        }

        return substr($tag, 0, $start) .
            static::renderTagAttributes($attributes) .
            substr($tag, $end);
    }

    /**
     * Parses an HTML tag to find its attributes.
     *
     * @param string $tag The HTML tag to parse
     * @param int $offset The offset to start looking for a tag
     * @param int|null $start The start position of the first attribute in the given tag
     * @param int|null $end The end position of the last attribute in the given tag
     * @param bool $decode Whether the attributes should be HTML decoded in the process
     * @return array The parsed HTML tag attributes
     * @throws InvalidArgumentException if `$tag` doesn't contain a valid HTML tag
     * @since 3.3.0
     */
    public static function parseTagAttributes(
        string $tag,
        int $offset = 0,
        ?int &$start = null,
        ?int &$end = null,
        bool $decode = false,
    ): array {
        [$type, $tagStart] = self::findTag($tag, $offset);
        $start = $tagStart + strlen($type) + 1;
        $anchor = $start;
        $attributes = [];

        do {
            try {
                $attribute = static::parseTagAttribute($tag, $anchor, $attrStart, $attrEnd);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage());
            }

            // Did we just reach the end of the tag?
            if ($attribute === null) {
                $end = $anchor;
                break;
            }

            [$name, $value] = $attribute;
            $attributes[$name] = $value;
            $anchor = $attrEnd;
        } while (true);

        $attributes = static::normalizeTagAttributes($attributes);

        if ($decode) {
            foreach ($attributes as &$value) {
                if (is_string($value)) {
                    $value = static::decode($value);
                }
            }
        }

        return $attributes;
    }

    /**
     * Parses the next HTML tag attribute in a given string.
     *
     * @param string $html The HTML to parse
     * @param int $offset The offset to start looking for an attribute
     * @param int|null $start The start position of the attribute in the given HTML
     * @param int|null $end The end position of the attribute in the given HTML
     * @return array|null The name and value of the attribute, or `null` if no more attributes
     * @throws InvalidArgumentException if `$html` doesn't begin with a valid HTML attribute
     * @since 3.7.0
     */
    public static function parseTagAttribute(
        string $html,
        int $offset = 0,
        ?int &$start = null,
        ?int &$end = null,
    ): ?array {
        if (!preg_match('/\s*([^=\/>\s]+)/A', $html, $match, PREG_OFFSET_CAPTURE, $offset)) {
            if (!preg_match('/(\s*)\/?>/A', $html, $m, 0, $offset)) {
                // No `>`
                throw new InvalidArgumentException("Malformed HTML tag attribute in string: $html");
            }

            // No more attributes here
            return null;
        }

        $value = true;

        // Does the tag have an explicit value?
        $offset += strlen($match[0][0]);

        if (preg_match('/\s*=\s*/A', $html, $m, 0, $offset)) {
            $offset += strlen($m[0]);

            // Wrapped in quotes?
            if (isset($html[$offset]) && in_array($html[$offset], ['\'', '"'])) {
                $q = preg_quote($html[$offset], '/');
                if (!preg_match("/$q(.*?)$q/sA", $html, $m, 0, $offset)) {
                    // No matching end quote
                    throw new InvalidArgumentException("Malformed HTML tag attribute in string: $html");
                }

                $offset += strlen($m[0]);
                if (isset($m[1]) && $m[1] !== '') {
                    $value = static::decode($m[1]);
                }
            } elseif (preg_match('/[^\s>]+/A', $html, $m, 0, $offset)) {
                $offset += strlen($m[0]);
                $value = static::decode($m[0]);
            }
        }

        $start = (int) $match[1][1];
        $end = $offset;

        return [$match[1][0], $value];
    }

    /**
     * Normalizes attributes.
     *
     * @param array $attributes
     * @return array
     * @since 3.3.0
     */
    public static function normalizeTagAttributes(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                $normalized[$name] = false;
                continue;
            }

            switch ($name) {
                case 'class':
                case 'remove_class':
                    $normalized[$name] = static::explodeClass($value);
                    break;
                case 'style':
                    $normalized[$name] = static::explodeStyle($value);
                    break;
                default:
                    // See if it's a data attribute
                    foreach (self::sortedDataAttributes() as $dataAttribute) {
                        if ($name === $dataAttribute) {
                            // Already in nested form (e.g. 'data-hx' => ['get' => '/url'])
                            $normalized[$name] = is_array($value)
                                ? array_merge($normalized[$name] ?? [], $value)
                                : $value;
                            break 2;
                        }
                        if (str_starts_with($name, $dataAttribute . '-')) {
                            $n = substr($name, strlen($dataAttribute) + 1);
                            $normalized[$dataAttribute][$n] = $value;
                            break 2;
                        }
                    }
                    $normalized[$name] = $value;
            }
        }

        if (isset($normalized['remove_class'])) {
            $removeClasses = ArrayHelper::remove($normalized, 'remove_class');
            $normalized['class'] = array_diff($normalized['class'] ?? [], $removeClasses);
        }

        return $normalized;
    }

    /**
     * Explodes a `class` attribute into an array.
     *
     * @param mixed $value
     * @return string[]
     * @since 3.5.0
     */
    public static function explodeClass(mixed $value): array
    {
        if ($value === null || is_bool($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            return self::filterEmptyStrings(explode(' ', $value));
        }
        throw new InvalidArgumentException('Invalid class value');
    }

    /**
     * Explodes a `style` attribute into an array of property/value pairs.
     *
     * @param mixed $value
     * @return string[]
     * @since 3.5.0
     */
    public static function explodeStyle(mixed $value): array
    {
        if ($value === null || is_bool($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            // first match any css properties that contain 'url()'
            $markers = [];
            $value = preg_replace_callback('/\burl\(.*\)/i', function ($match) use (&$markers) {
                $marker = sprintf('{marker:%s}', mt_rand());
                $markers[$marker] = $match[0];
                return $marker;
            }, $value);

            // now split the styles string on semicolons
            $styles = self::filterEmptyStrings(preg_split('/\s*;\s*/', $value));

            // and proceed with the array of styles
            $normalized = [];
            foreach ($styles as $style) {
                [$n, $v] = array_pad(preg_split('/\s*:\s*/', $style, 2), 2, '');
                $normalized[$n] = strtr($v, $markers);
            }
            return $normalized;
        }
        throw new InvalidArgumentException('Invalid style value');
    }

    /**
     * Filters empty strings from an array.
     *
     * Equivalent to Craft's `ArrayHelper::filterEmptyStringsFromArray()`.
     *
     * @param array $array
     * @return array
     */
    private static function filterEmptyStrings(array $array): array
    {
        return array_values(array_filter($array, fn(string $v): bool => $v !== ''));
    }

    /**
     * Expands data-attribute arrays for prefixes that yiisoft/html doesn't handle natively.
     *
     * yiisoft/html's renderTagAttributes() only expands: data, data-ng, ng, aria.
     * Craft's $dataAttributes also includes: data-hx, hx. We expand those ourselves
     * so that e.g. ['hx' => ['get' => '/url']] renders as hx-get="/url" rather than
     * being JSON-encoded.
     */
    private static function expandDataAttributes(array $attributes): array
    {
        // Prefixes that yiisoft/html handles natively (Html::DATA_ATTRIBUTES)
        static $nativePrefixes = ['data', 'data-ng', 'ng', 'aria'];

        foreach (static::$dataAttributes as $prefix) {
            if (
                !in_array($prefix, $nativePrefixes, true) &&
                isset($attributes[$prefix]) &&
                is_array($attributes[$prefix])
            ) {
                foreach ($attributes[$prefix] as $n => $v) {
                    $attributes["$prefix-$n"] = $v;
                }
                unset($attributes[$prefix]);
            }
        }

        return $attributes;
    }

    /**
     * Finds the first tag defined in some HTML that isn't a comment or DTD.
     *
     * @param string $html
     * @param int $offset
     * @return array{string, int} The tag type and starting position
     * @throws InvalidArgumentException
     */
    private static function findTag(string $html, int $offset = 0): array
    {
        // Find the first HTML tag that isn't a DTD or a comment
        if (!preg_match('/<(\/?[\w\-]+)/', $html, $match, PREG_OFFSET_CAPTURE, $offset) || $match[1][0][0] === '/') {
            throw new InvalidArgumentException("Could not find an HTML tag in string: $html");
        }

        return [strtolower($match[1][0]), $match[0][1]];
    }

    /**
     * @return string[]
     */
    private static function sortedDataAttributes(): array
    {
        if (!isset(self::$sortedDataAttributes)) {
            self::$sortedDataAttributes = array_merge(static::$dataAttributes);
            usort(self::$sortedDataAttributes, fn(string $a, string $b): int => strlen($b) - strlen($a));
        }
        return self::$sortedDataAttributes;
    }
}
