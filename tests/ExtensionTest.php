<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests;

use ArrayIterator;
use eSheep\Twigs\Extension;
use PHPUnit\Framework\TestCase;
use TalesFromADev\TailwindMerge\TailwindMergeInterface;
use Twig\Extra\Html\Cva;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class ExtensionTest extends TestCase
{
    private Extension $ext;

    protected function setUp(): void
    {
        $this->ext = new Extension();
    }

    // --- Registration ---

    public function testGetFunctionsReturnsExpectedNames(): void
    {
        $names = array_map(fn(TwigFunction $f) => $f->getName(), $this->ext->getFunctions());
        self::assertSame(['html_cva', 'cva', 'html_tag', 'tag', 'html_attr', 'attr', 'ray'], $names);
    }

    public function testGetFiltersReturnsExpectedNames(): void
    {
        $names = array_map(fn(TwigFilter $f) => $f->getName(), $this->ext->getFilters());
        self::assertSame(['html_attr', 'attr', 'merge_attributes', 'tailwind_merge', 'tw_merge', 'ray'], $names);
    }

    public function testGetTokenParsersReturnsTwo(): void
    {
        $parsers = $this->ext->getTokenParsers();
        self::assertCount(2, $parsers);
        $tags = array_map(fn($p) => $p->getTag(), $parsers);
        self::assertSame(['tag', 'html_tag'], $tags);
    }

    // --- cva() ---

    public function testCvaReturnsCvaInstance(): void
    {
        self::assertInstanceOf(Cva::class, Extension::cva('base'));
    }

    // --- tagFunction() ---

    public function testTagFunctionWithHtml(): void
    {
        $result = Extension::tagFunction('div', ['html' => '<b>bold</b>', 'class' => 'x']);
        self::assertSame('<div class="x"><b>bold</b></div>', $result);
    }

    public function testTagFunctionWithText(): void
    {
        $result = Extension::tagFunction('div', ['text' => '<script>']);
        self::assertStringNotContainsString('<script>', $result);
        self::assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testTagFunctionTextOverridesHtml(): void
    {
        $result = Extension::tagFunction('div', ['html' => 'raw', 'text' => 'encoded']);
        self::assertStringContainsString('encoded', $result);
        self::assertStringNotContainsString('raw', $result);
    }

    public function testTagFunctionRemovesHtmlAndTextFromAttributes(): void
    {
        $result = Extension::tagFunction('div', ['html' => 'content', 'text' => null]);
        self::assertStringNotContainsString('html=', $result);
        self::assertStringNotContainsString('text=', $result);
    }

    public function testTagFunctionEmptyTag(): void
    {
        self::assertSame('<div></div>', Extension::tagFunction('div'));
    }

    // --- attrFilter() ---

    public function testAttrFilterModifiesTag(): void
    {
        $result = Extension::attrFilter('<div class="a">', ['id' => 'x']);
        self::assertStringContainsString('id="x"', $result);
    }

    public function testAttrFilterReturnsOriginalOnInvalidHtml(): void
    {
        $original = 'not a tag';
        // Suppress the E_USER_WARNING
        $result = @Extension::attrFilter($original, ['id' => 'x']);
        self::assertSame($original, $result);
    }

    public function testAttrFilterTriggersWarningOnInvalidHtml(): void
    {
        $warning = null;
        set_error_handler(function (int $errno, string $errstr) use (&$warning) {
            $warning = $errstr;
            return true;
        }, E_USER_WARNING);

        try {
            Extension::attrFilter('not a tag', ['id' => 'x']);
        } finally {
            restore_error_handler();
        }

        self::assertNotNull($warning);
        self::assertStringContainsString('invalid HTML tag', $warning);
    }

    // --- mergeAttributes() ---

    public function testMergeAttributesBasic(): void
    {
        $result = $this->ext->mergeAttributes(
            ['id' => 'a'],
            ['data-x' => '1'],
        );
        self::assertSame('a', $result['id']);
        self::assertSame(['x' => '1'], $result['data']);
    }

    public function testMergeAttributesDeduplicatesClasses(): void
    {
        $result = $this->ext->mergeAttributes(
            ['class' => 'a b'],
            ['class' => 'b c'],
        );
        $classes = array_values($result['class']);
        self::assertSame(['a', 'b', 'c'], $classes);
    }

    public function testMergeAttributesTailwindMerge(): void
    {
        $result = $this->ext->mergeAttributes(
            ['class' => 'p-4 text-red-500'],
            ['class' => 'p-2 font-bold'],
        );
        $classes = array_values($result['class']);
        self::assertContains('p-2', $classes);
        self::assertNotContains('p-4', $classes);
        self::assertContains('text-red-500', $classes);
        self::assertContains('font-bold', $classes);
    }

    public function testMergeAttributesTailwindMergeDisabledPerCall(): void
    {
        $result = $this->ext->mergeAttributes(
            ['class' => 'p-4'],
            ['class' => 'p-2'],
            ['tailwind_merge' => false],
        );
        $classes = array_values($result['class']);
        self::assertContains('p-4', $classes);
        self::assertContains('p-2', $classes);
    }

    public function testMergeAttributesDeduplicationDisabledPerCall(): void
    {
        // With dedup off but same values at same integer keys,
        // ArrayHelper::merge still skips identical values at same key.
        // Use different base classes to test that dedup flag controls array_unique.
        $result = $this->ext->mergeAttributes(
            ['class' => 'a b'],
            ['class' => 'b c'],
            ['remove_duplicate_classes' => false, 'tailwind_merge' => false],
        );
        $classes = array_values($result['class']);
        // ArrayHelper::merge appends 'c' (different from key 1 'b'),
        // but 'b' at key 0 of second array equals 'a' at key 0 of first? No —
        // keys are [0=>'a', 1=>'b'] merged with [0=>'b', 1=>'c']:
        // key 0: 'a' !== 'b' → append 'b'; key 1: 'b' !== 'c' → append 'c'
        // Result: ['a', 'b', 'b', 'c'] — 'b' appears twice because dedup is off
        self::assertSame(2, count(array_filter($classes, fn($c) => $c === 'b')));
    }

    public function testMergeAttributesConstructorDefaults(): void
    {
        $ext = new Extension(mergeOptions: [
            'tailwind_merge' => false,
            'remove_duplicate_classes' => false,
        ]);
        $result = $ext->mergeAttributes(
            ['class' => 'p-4 a'],
            ['class' => 'p-2 b'],
        );
        $classes = array_values($result['class']);
        // Both p-4 and p-2 should be present (no tw merge)
        self::assertContains('p-4', $classes);
        self::assertContains('p-2', $classes);
        self::assertContains('a', $classes);
        self::assertContains('b', $classes);
    }

    public function testMergeAttributesPerCallOverridesConstructor(): void
    {
        $ext = new Extension(mergeOptions: ['tailwind_merge' => false]);
        $result = $ext->mergeAttributes(
            ['class' => 'p-4'],
            ['class' => 'p-2'],
            ['tailwind_merge' => true],
        );
        $classes = array_values($result['class']);
        self::assertNotContains('p-4', $classes);
        self::assertContains('p-2', $classes);
    }

    public function testMergeAttributesTraversable(): void
    {
        $result = $this->ext->mergeAttributes(
            new ArrayIterator(['class' => 'foo', 'id' => 'x']),
            new ArrayIterator(['class' => 'bar']),
        );
        self::assertSame('x', $result['id']);
        self::assertContains('foo', $result['class']);
        self::assertContains('bar', $result['class']);
    }

    public function testMergeAttributesStyleMerge(): void
    {
        $result = $this->ext->mergeAttributes(
            ['style' => 'color: red'],
            ['style' => 'font-size: 14px'],
        );
        self::assertSame('red', $result['style']['color']);
        self::assertSame('14px', $result['style']['font-size']);
    }

    // --- tailwindMerge() ---

    public function testTailwindMergeBasic(): void
    {
        $result = $this->ext->tailwindMerge('p-2 p-4');
        self::assertSame('p-4', $result);
    }

    public function testTailwindMergeVariadic(): void
    {
        $result = $this->ext->tailwindMerge('p-2', ['p-4', 'mt-2']);
        self::assertStringContainsString('p-4', $result);
        self::assertStringContainsString('mt-2', $result);
        self::assertStringNotContainsString('p-2', $result);
    }

    public function testTailwindMergeUsesInjectedInstance(): void
    {
        $mock = $this->createMock(TailwindMergeInterface::class);
        $mock->expects(self::once())
            ->method('merge')
            ->with('a', 'b')
            ->willReturn('merged');

        $ext = new Extension(tailwindMerge: $mock);
        self::assertSame('merged', $ext->tailwindMerge('a', ['b']));
    }
}
