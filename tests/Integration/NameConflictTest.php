<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests\Integration;

use eSheep\Twigs\Extension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\ExtensionInterface;
use Twig\Extra\Html\HtmlExtension;
use Twig\Loader\ArrayLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Our functions and filters must win over same-named ones from other extensions,
 * whatever order the extensions are registered in.
 */
final class NameConflictTest extends TestCase
{
    public static function registrationOrders(): iterable
    {
        yield 'ours registered first' => [true];
        yield 'ours registered last' => [false];
    }

    #[DataProvider('registrationOrders')]
    public function testHtmlAttrFromHtmlExtraDoesNotOverrideOurs(bool $oursFirst): void
    {
        $twig = $this->twig($oursFirst, new HtmlExtension());

        self::assertSame(
            '<div class="a" hx-get="/x">',
            $twig->createTemplate("<div{{ html_attr({class: 'a b', hx: {get: '/x'}, remove_class: 'b'}) }}>")->render(),
        );
        self::assertSame('base', $twig->createTemplate("{{ html_cva('base').apply({}) }}")->render());
    }

    #[DataProvider('registrationOrders')]
    public function testOtherExtensionsDoNotOverrideOurNames(bool $oursFirst): void
    {
        $rival = new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [new TwigFunction('tag', fn() => 'rival'), new TwigFunction('attr', fn() => 'rival')];
            }

            public function getFilters(): array
            {
                return [new TwigFilter('attr', fn() => 'rival'), new TwigFilter('tw_merge', fn() => 'rival')];
            }
        };
        $twig = $this->twig($oursFirst, $rival);

        self::assertSame(
            '<p>x</p> <i id="y"> p-4 class="a"',
            $twig->createTemplate("{{ tag('p', 'x') }} {{ '<i>'|attr({id: 'y'}) }} {{ 'p-2 p-4'|tw_merge }}{{ attr({class: 'a'}) }}")->render(),
        );
    }

    #[DataProvider('registrationOrders')]
    public function testOverriddenNamesKeepOurEscaping(bool $oursFirst): void
    {
        // A same-named function that isn't marked safe would get its output escaped.
        $rival = new class extends AbstractExtension {
            public function getFunctions(): array
            {
                return [new TwigFunction('tag', fn() => '<rival>')];
            }
        };
        $twig = $this->twig($oursFirst, $rival);

        self::assertSame('<p>x</p>', $twig->createTemplate("{{ tag('p', 'x') }}")->render());
    }

    public function testNamesWeDoNotDefineAreLeftAlone(): void
    {
        $twig = $this->twig(false, new HtmlExtension());

        self::assertSame('foo bar', $twig->createTemplate("{{ html_classes('foo', {bar: true}) }}")->render());
    }

    private function twig(bool $oursFirst, ExtensionInterface $other): Environment
    {
        $twig = new Environment(new ArrayLoader());
        foreach ($oursFirst ? [new Extension(), $other] : [$other, new Extension()] as $extension) {
            $twig->addExtension($extension);
        }

        return $twig;
    }
}
