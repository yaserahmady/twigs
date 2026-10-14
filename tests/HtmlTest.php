<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests;

use eSheep\Twigs\Html;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase
{
    // --- tag() ---

    public function testTagBasic(): void
    {
        self::assertSame('<div>Hello</div>', Html::tag('div', 'Hello'));
    }

    public function testTagWithAttributes(): void
    {
        $result = Html::tag('div', '', ['class' => 'foo', 'id' => 'bar']);
        self::assertStringContainsString('class="foo"', $result);
        self::assertStringContainsString('id="bar"', $result);
        self::assertStringStartsWith('<div ', $result);
        self::assertStringEndsWith('></div>', $result);
    }

    public function testTagVoidElement(): void
    {
        self::assertSame('<br>', Html::tag('br'));
        self::assertSame('<img src="a.png">', Html::tag('img', '', ['src' => 'a.png']));
    }

    public function testTagVoidElementIgnoresContent(): void
    {
        self::assertSame('<br>', Html::tag('br', 'ignored'));
    }

    public function testTagVoidCheckIsCaseInsensitive(): void
    {
        // Tag name is preserved as given, but void check is lowercased
        self::assertSame('<BR>', Html::tag('BR'));
    }

    public function testTagEmptyContent(): void
    {
        self::assertSame('<div></div>', Html::tag('div'));
    }

    public function testTagRawHtmlContent(): void
    {
        self::assertSame('<p><b>bold</b></p>', Html::tag('p', '<b>bold</b>'));
    }

    // --- beginTag() / endTag() ---

    public function testBeginTag(): void
    {
        self::assertSame('<div class="x">', Html::beginTag('div', ['class' => 'x']));
    }

    public function testEndTag(): void
    {
        self::assertSame('</div>', Html::endTag('div'));
    }

    // --- renderTagAttributes() ---

    public function testRenderTagAttributesEmpty(): void
    {
        self::assertSame('', Html::renderTagAttributes([]));
    }

    public function testRenderTagAttributesLeadingSpace(): void
    {
        $result = Html::renderTagAttributes(['id' => 'foo']);
        self::assertStringStartsWith(' ', $result);
        self::assertStringContainsString('id="foo"', $result);
    }

    public function testRenderTagAttributesBooleanTrue(): void
    {
        $result = Html::renderTagAttributes(['disabled' => true]);
        self::assertStringContainsString('disabled', $result);
        self::assertStringNotContainsString('disabled=', $result);
    }

    public function testRenderTagAttributesBooleanFalse(): void
    {
        self::assertSame('', Html::renderTagAttributes(['disabled' => false]));
    }

    public function testRenderTagAttributesNull(): void
    {
        self::assertSame('', Html::renderTagAttributes(['disabled' => null]));
    }

    public function testRenderTagAttributesClassString(): void
    {
        $result = Html::renderTagAttributes(['class' => 'a b c']);
        self::assertStringContainsString('class="a b c"', $result);
    }

    public function testRenderTagAttributesClassArray(): void
    {
        $result = Html::renderTagAttributes(['class' => ['a', 'b']]);
        self::assertStringContainsString('class="a b"', $result);
    }

    public function testRenderTagAttributesStyleString(): void
    {
        $result = Html::renderTagAttributes(['style' => 'color: red; font-size: 12px']);
        self::assertStringContainsString('style="color: red; font-size: 12px;"', $result);
    }

    public function testRenderTagAttributesStyleArray(): void
    {
        $result = Html::renderTagAttributes(['style' => ['color' => 'red']]);
        self::assertStringContainsString('style="color: red;"', $result);
    }

    public function testRenderTagAttributesDataExpansion(): void
    {
        $result = Html::renderTagAttributes(['data' => ['id' => '5', 'name' => 'test']]);
        self::assertStringContainsString('data-id="5"', $result);
        self::assertStringContainsString('data-name="test"', $result);
    }

    public function testRenderTagAttributesHxExpansion(): void
    {
        $result = Html::renderTagAttributes(['hx' => ['get' => '/url', 'swap' => 'innerHTML']]);
        self::assertStringContainsString('hx-get="/url"', $result);
        self::assertStringContainsString('hx-swap="innerHTML"', $result);
    }

    public function testRenderTagAttributesDataHxExpansion(): void
    {
        $result = Html::renderTagAttributes(['data-hx' => ['get' => '/url']]);
        self::assertStringContainsString('data-hx-get="/url"', $result);
    }

    public function testRenderTagAttributesAriaExpansion(): void
    {
        $result = Html::renderTagAttributes(['aria' => ['label' => 'Close']]);
        self::assertStringContainsString('aria-label="Close"', $result);
    }

    public function testRenderTagAttributesHtmlEncoding(): void
    {
        $result = Html::renderTagAttributes(['title' => 'a & "b"']);
        self::assertStringContainsString('title="a &amp; &quot;b&quot;"', $result);
    }

    // --- normalizeTagAttributes() ---

    public function testNormalizeClassString(): void
    {
        $result = Html::normalizeTagAttributes(['class' => 'a b c']);
        self::assertSame(['a', 'b', 'c'], $result['class']);
    }

    public function testNormalizeClassArray(): void
    {
        $result = Html::normalizeTagAttributes(['class' => ['a', 'b']]);
        self::assertSame(['a', 'b'], $result['class']);
    }

    public function testNormalizeClassWithExtraSpaces(): void
    {
        $result = Html::normalizeTagAttributes(['class' => 'a  b   c']);
        self::assertSame(['a', 'b', 'c'], $result['class']);
    }

    public function testNormalizeStyleString(): void
    {
        $result = Html::normalizeTagAttributes(['style' => 'color: red; font-size: 12px']);
        self::assertSame(['color' => 'red', 'font-size' => '12px'], $result['style']);
    }

    public function testNormalizeFalseValue(): void
    {
        $result = Html::normalizeTagAttributes(['disabled' => false]);
        self::assertFalse($result['disabled']);
    }

    public function testNormalizeNullValue(): void
    {
        $result = Html::normalizeTagAttributes(['disabled' => null]);
        self::assertFalse($result['disabled']);
    }

    public function testNormalizeDataAttributeCollapsing(): void
    {
        $result = Html::normalizeTagAttributes(['data-id' => '5', 'data-name' => 'test']);
        self::assertSame(['id' => '5', 'name' => 'test'], $result['data']);
    }

    public function testNormalizeHxAttributeCollapsing(): void
    {
        $result = Html::normalizeTagAttributes(['hx-get' => '/url']);
        self::assertSame(['get' => '/url'], $result['hx']);
    }

    public function testNormalizeDataHxAttributeCollapsing(): void
    {
        $result = Html::normalizeTagAttributes(['data-hx-get' => '/url']);
        self::assertSame(['get' => '/url'], $result['data-hx']);
        // Must NOT be parsed as data => ['hx-get' => '/url']
        self::assertArrayNotHasKey('data', $result);
    }

    public function testNormalizeRemoveClass(): void
    {
        $result = Html::normalizeTagAttributes(['class' => 'a b c', 'remove_class' => 'b']);
        self::assertSame(['a', 'c'], array_values($result['class']));
        self::assertArrayNotHasKey('remove_class', $result);
    }

    public function testNormalizeRemoveClassArray(): void
    {
        $result = Html::normalizeTagAttributes(['class' => 'a b c', 'remove_class' => ['a', 'c']]);
        self::assertSame(['b'], array_values($result['class']));
    }

    public function testNormalizeRemoveClassNoMatchingClass(): void
    {
        $result = Html::normalizeTagAttributes(['remove_class' => 'a']);
        self::assertSame([], array_values($result['class']));
    }

    // --- explodeClass() ---

    public function testExplodeClassString(): void
    {
        self::assertSame(['a', 'b', 'c'], Html::explodeClass('a b c'));
    }

    public function testExplodeClassArray(): void
    {
        self::assertSame(['x'], Html::explodeClass(['x']));
    }

    public function testExplodeClassNull(): void
    {
        self::assertSame([], Html::explodeClass(null));
    }

    public function testExplodeClassBool(): void
    {
        self::assertSame([], Html::explodeClass(true));
        self::assertSame([], Html::explodeClass(false));
    }

    public function testExplodeClassEmpty(): void
    {
        self::assertSame([], Html::explodeClass(''));
    }

    public function testExplodeClassInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Html::explodeClass(42);
    }

    // --- explodeStyle() ---

    public function testExplodeStyleString(): void
    {
        self::assertSame(
            ['color' => 'red', 'font-size' => '12px'],
            Html::explodeStyle('color: red; font-size: 12px'),
        );
    }

    public function testExplodeStyleArray(): void
    {
        $input = ['color' => 'red'];
        self::assertSame($input, Html::explodeStyle($input));
    }

    public function testExplodeStyleNull(): void
    {
        self::assertSame([], Html::explodeStyle(null));
    }

    public function testExplodeStyleWithUrl(): void
    {
        $result = Html::explodeStyle('background: url(http://example.com/img.png); color: red');
        self::assertSame('red', $result['color']);
        self::assertStringContainsString('url(http://example.com/img.png)', $result['background']);
    }

    public function testExplodeStyleTrailingSemicolon(): void
    {
        $result = Html::explodeStyle('color: red;');
        self::assertSame(['color' => 'red'], $result);
    }

    public function testExplodeStyleEmpty(): void
    {
        self::assertSame([], Html::explodeStyle(''));
    }

    public function testExplodeStyleInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Html::explodeStyle(42);
    }

    // --- modifyTagAttributes() ---

    public function testModifyTagAttributesAddNew(): void
    {
        $result = Html::modifyTagAttributes('<div class="a">', ['id' => 'x']);
        self::assertStringContainsString('id="x"', $result);
        self::assertStringContainsString('class="a"', $result);
    }

    public function testModifyTagAttributesMergeClasses(): void
    {
        $result = Html::modifyTagAttributes('<div class="a">', ['class' => 'b']);
        self::assertStringContainsString('class="a b"', $result);
    }

    public function testModifyTagAttributesDeduplicatesClasses(): void
    {
        $result = Html::modifyTagAttributes('<div class="a b">', ['class' => 'a']);
        // 'a' should appear only once
        self::assertSame(1, substr_count($result, '"a ') + substr_count($result, ' a"') + substr_count($result, '"a"'));
    }

    public function testModifyTagAttributesVoidElement(): void
    {
        $result = Html::modifyTagAttributes('<img src="a.png">', ['alt' => 'test']);
        self::assertStringContainsString('alt="test"', $result);
        self::assertStringContainsString('src="a.png"', $result);
    }

    public function testModifyTagAttributesPreservesContent(): void
    {
        $result = Html::modifyTagAttributes('<div class="a">Hello</div>', ['id' => 'x']);
        self::assertStringContainsString('>Hello</div>', $result);
    }

    public function testModifyTagAttributesThrowsOnInvalidTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Html::modifyTagAttributes('no tag here', ['id' => 'x']);
    }

    // --- parseTagAttributes() ---

    public function testParseTagAttributesSimple(): void
    {
        $attrs = Html::parseTagAttributes('<div class="foo" id="bar">');
        self::assertSame(['foo'], $attrs['class']);
        self::assertSame('bar', $attrs['id']);
    }

    public function testParseTagAttributesBoolean(): void
    {
        $attrs = Html::parseTagAttributes('<input disabled>');
        self::assertTrue($attrs['disabled']);
    }

    public function testParseTagAttributesUnquoted(): void
    {
        $attrs = Html::parseTagAttributes('<div class=foo>');
        self::assertSame(['foo'], $attrs['class']);
    }

    public function testParseTagAttributesSingleQuoted(): void
    {
        $attrs = Html::parseTagAttributes("<div class='foo'>");
        self::assertSame(['foo'], $attrs['class']);
    }

    public function testParseTagAttributesDecode(): void
    {
        $attrs = Html::parseTagAttributes('<div title="a &amp; b">', decode: true);
        self::assertSame('a & b', $attrs['title']);
    }

    public function testParseTagAttributesStartEnd(): void
    {
        Html::parseTagAttributes('<div class="foo">', start: $start, end: $end);
        self::assertIsInt($start);
        self::assertIsInt($end);
        self::assertGreaterThan(0, $start);
        self::assertGreaterThan($start, $end);
    }

    public function testParseTagAttributesThrowsOnNoTag(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Html::parseTagAttributes('no tag here');
    }

    // --- parseTagAttribute() ---

    public function testParseTagAttributeKeyValue(): void
    {
        $result = Html::parseTagAttribute(' class="foo">', 0, $start, $end);
        self::assertSame(['class', 'foo'], $result);
    }

    public function testParseTagAttributeBooleanAttr(): void
    {
        $result = Html::parseTagAttribute(' disabled>', 0, $start, $end);
        self::assertSame(['disabled', true], $result);
    }

    public function testParseTagAttributeEndOfTag(): void
    {
        $result = Html::parseTagAttribute('>', 0, $start, $end);
        self::assertNull($result);
    }

    public function testParseTagAttributeSelfClosingEnd(): void
    {
        $result = Html::parseTagAttribute('/>', 0, $start, $end);
        self::assertNull($result);
    }

    public function testParseTagAttributeMalformed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Html::parseTagAttribute('class="no end quote', 0, $start, $end);
    }

    // --- encode() / decode() ---

    public function testEncodeDecodeRoundTrip(): void
    {
        $original = 'Hello <world> & "friends"';
        self::assertSame($original, Html::decode(Html::encode($original)));
    }

    public function testEncodeDoubleEncodeFalse(): void
    {
        $result = Html::encode('&amp;', doubleEncode: false);
        self::assertSame('&amp;', $result);
    }

    // --- hx-* round-trip through normalize + render ---

    public function testHxRoundTrip(): void
    {
        $tag = Html::tag('button', 'Go', ['hx-get' => '/api', 'hx-swap' => 'innerHTML']);
        self::assertStringContainsString('hx-get="/api"', $tag);
        self::assertStringContainsString('hx-swap="innerHTML"', $tag);
    }

    public function testDataHxRoundTrip(): void
    {
        $tag = Html::tag('button', 'Go', ['data-hx-get' => '/api']);
        self::assertStringContainsString('data-hx-get="/api"', $tag);
    }

    public function testModifyHxAttributes(): void
    {
        $result = Html::modifyTagAttributes(
            '<div hx-get="/old">',
            ['hx-get' => '/new', 'hx-swap' => 'outerHTML'],
        );
        self::assertStringContainsString('hx-get="/new"', $result);
        self::assertStringContainsString('hx-swap="outerHTML"', $result);
        self::assertStringNotContainsString('/old', $result);
    }
}
