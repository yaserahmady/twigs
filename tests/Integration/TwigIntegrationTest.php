<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests\Integration;

use eSheep\Twigs\Extension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Loader\ArrayLoader;

final class TwigIntegrationTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = new Environment(new ArrayLoader(), [
            'autoescape' => false,
        ]);
        $this->twig->addExtension(new Extension());
    }

    private function render(string $template, array $context = []): string
    {
        $tpl = $this->twig->createTemplate($template);
        return $tpl->render($context);
    }

    // --- Functions ---

    public function testTagFunction(): void
    {
        $result = $this->render("{{ tag('div', {class: 'foo', html: 'Hello'}) }}");
        self::assertSame('<div class="foo">Hello</div>', $result);
    }

    public function testHtmlTagFunction(): void
    {
        $result = $this->render("{{ html_tag('span', {text: '<em>x</em>'}) }}");
        self::assertStringContainsString('&lt;em&gt;x&lt;/em&gt;', $result);
        self::assertStringStartsWith('<span>', $result);
    }

    public function testTagFunctionStringShortcut(): void
    {
        self::assertSame('<p>Hello</p>', $this->render("{{ tag('p', 'Hello') }}"));
        // yiisoft/html's encode() leaves quotes as-is (ENT_NOQUOTES), which is safe for tag content
        self::assertSame(
            "<p>&lt;script&gt;alert('Hello');&lt;/script&gt;</p>",
            $this->render("{{ tag('p', \"<script>alert('Hello');</script>\") }}"),
        );
    }

    public function testAttrFunction(): void
    {
        $result = $this->render("{{ attr({class: 'a b', id: 'x'}) }}");
        self::assertStringContainsString('class="a b"', $result);
        self::assertStringContainsString('id="x"', $result);
    }

    public function testCvaFunction(): void
    {
        $result = $this->render(
            "{{ cva('base', {size: {sm: 'text-sm', lg: 'text-lg'}}, [], {size: 'sm'}).apply({size: 'lg'}) }}",
        );
        self::assertStringContainsString('base', $result);
        self::assertStringContainsString('text-lg', $result);
    }

    // --- Filters ---

    public function testAttrFilter(): void
    {
        $result = $this->render("{{ '<div class=\"a\">Hi</div>'|attr({class: 'b'}) }}");
        self::assertStringContainsString('class="a b"', $result);
        self::assertStringContainsString('>Hi</div>', $result);
    }

    public function testAttrFilterOnPlainString(): void
    {
        self::assertSame('Hey', $this->render("{{ 'Hey'|attr({class: 'foo'}) }}"));
    }

    public function testRemoveClassFilter(): void
    {
        self::assertSame('<div>', $this->render("{{ '<div class=\"foo\">'|remove_class('foo') }}"));
        self::assertSame('<div class="bar">', $this->render("{{ '<div class=\"foo bar\">'|remove_class('foo') }}"));
        self::assertSame('<div class="baz">', $this->render("{{ '<div class=\"foo bar baz\">'|remove_class(['foo', 'bar']) }}"));
        self::assertSame('foo', $this->render("{{ 'foo'|remove_class('foo') }}"));
    }

    public function testRayFilterPassesValueThroughWithSameEscaping(): void
    {
        $twig = new Environment(new ArrayLoader());
        $twig->addExtension(new Extension());
        $render = fn(string $template) => $twig->createTemplate($template)->render(['v' => '<b>', 'n' => 42]);

        $errorLog = ini_set('error_log', '/dev/null');
        try {
            self::assertSame($render('{{ v }}'), $render('{{ v|ray }}'));
            self::assertSame('&lt;b&gt;', $render('{{ v|ray }}'));
            self::assertSame("<p>x</p>", $render("{{ tag('p', 'x')|ray }}"));
            self::assertSame('42', $render("{{ n|ray('label') }}"));
            self::assertSame('kept', $render("{% set x = 'kept'|ray %}{{ x }}"));
            self::assertSame('', $render('{{ ray(v) }}'));
        } finally {
            ini_set('error_log', $errorLog);
        }
    }

    public function testTwMergeFilter(): void
    {
        $result = $this->render("{{ 'p-2 p-4'|tw_merge }}");
        self::assertSame('p-4', trim($result));
    }

    public function testTailwindMergeFilterVariadic(): void
    {
        $result = $this->render("{{ 'p-2'|tailwind_merge('p-4', 'mt-2') }}");
        self::assertStringContainsString('p-4', $result);
        self::assertStringContainsString('mt-2', $result);
    }

    public function testMergeAttributesFilter(): void
    {
        $result = $this->render(
            "{{ base|merge_attributes(extra).id }}",
            ['base' => ['class' => 'a', 'id' => 'x'], 'extra' => ['class' => 'b']],
        );
        self::assertSame('x', $result);
    }

    public function testMergeAttributesThenRender(): void
    {
        $result = $this->render(
            "<div{{ attr(base|merge_attributes(extra)) }}>",
            ['base' => ['class' => 'a', 'id' => 'x'], 'extra' => ['class' => 'b']],
        );
        self::assertStringContainsString('class="a b"', $result);
        self::assertStringContainsString('id="x"', $result);
    }

    public function testMergeAttributesWithOptions(): void
    {
        $result = $this->render(
            "{{ attr(base|merge_attributes(extra, {tailwind_merge: false})) }}",
            ['base' => ['class' => 'p-4'], 'extra' => ['class' => 'p-2']],
        );
        self::assertStringContainsString('p-4', $result);
        self::assertStringContainsString('p-2', $result);
    }

    // --- Tag blocks ---

    public function testTagBlock(): void
    {
        $result = $this->render("{% tag 'div' with {class: 'foo'} %}Hello{% endtag %}");
        self::assertSame('<div class="foo">Hello</div>', $result);
    }

    public function testHtmlTagBlock(): void
    {
        $result = $this->render("{% html_tag 'span' with {id: 'bar'} %}World{% endhtml_tag %}");
        self::assertSame('<span id="bar">World</span>', $result);
    }

    public function testTagBlockWithoutWith(): void
    {
        $result = $this->render("{% tag 'p' %}Text{% endtag %}");
        self::assertSame('<p>Text</p>', $result);
    }

    public function testNestedTagBlocks(): void
    {
        $result = $this->render("{% tag 'div' %}{% tag 'span' %}inner{% endtag %}{% endtag %}");
        self::assertSame('<div><span>inner</span></div>', $result);
    }

    public function testNestedMixedTagBlocks(): void
    {
        $result = $this->render("{% html_tag 'div' %}{% tag 'span' %}inner{% endtag %}{% endhtml_tag %}");
        self::assertSame('<div><span>inner</span></div>', $result);
    }

    public function testHtmlTagBlockRejectsEndtag(): void
    {
        $this->expectException(SyntaxError::class);
        $this->render("{% html_tag 'span' %}World{% endtag %}");
    }

    public function testTagBlockRejectsEndhtmlTag(): void
    {
        $this->expectException(SyntaxError::class);
        $this->render("{% tag 'span' %}World{% endhtml_tag %}");
    }

    public function testTagBlockDynamicName(): void
    {
        $result = $this->render("{% set t = 'h1' %}{% tag t %}Title{% endtag %}");
        self::assertSame('<h1>Title</h1>', $result);
    }

    public function testTagBlockVoidElement(): void
    {
        $result = $this->render("{% tag 'br' %}{% endtag %}");
        self::assertSame('<br>', $result);
    }

    // --- Alias equivalence ---

    public function testTagAndHtmlTagAliasEquivalence(): void
    {
        $a = $this->render("{{ tag('div', {class: 'x', html: 'y'}) }}");
        $b = $this->render("{{ html_tag('div', {class: 'x', html: 'y'}) }}");
        self::assertSame($a, $b);
    }

    public function testAttrFunctionAliasEquivalence(): void
    {
        $a = $this->render("{{ attr({class: 'x'}) }}");
        $b = $this->render("{{ html_attr({class: 'x'}) }}");
        self::assertSame($a, $b);
    }

    public function testAttrFilterAliasEquivalence(): void
    {
        $a = $this->render("{{ '<div>'|attr({class: 'x'}) }}");
        $b = $this->render("{{ '<div>'|html_attr({class: 'x'}) }}");
        self::assertSame($a, $b);
    }

    public function testRemoveClassFilterAliasEquivalence(): void
    {
        $a = $this->render("{{ '<div class=\"x y\">'|remove_class('x') }}");
        $b = $this->render("{{ '<div class=\"x y\">'|html_remove_class('x') }}");
        self::assertSame($a, $b);
    }

    public function testTwMergeAliasEquivalence(): void
    {
        $a = $this->render("{{ 'p-2 p-4'|tw_merge }}");
        $b = $this->render("{{ 'p-2 p-4'|tailwind_merge }}");
        self::assertSame($a, $b);
    }

    public function testCvaAliasEquivalence(): void
    {
        $a = $this->render("{{ cva('base').apply({})|trim }}");
        $b = $this->render("{{ html_cva('base').apply({})|trim }}");
        self::assertSame($a, $b);
    }

    // --- Edge cases ---

    public function testHxAttributesInTemplate(): void
    {
        $result = $this->render(
            "{{ tag('button', {hx: {'get': '/api', 'swap': 'innerHTML'}, html: 'Click'}) }}",
        );
        self::assertStringContainsString('hx-get="/api"', $result);
        self::assertStringContainsString('hx-swap="innerHTML"', $result);
        self::assertStringContainsString('>Click</button>', $result);
    }

    public function testDataHxAttributesInTemplate(): void
    {
        $result = $this->render("{{ attr({'data-hx': {'get': '/url'}}) }}");
        self::assertStringContainsString('data-hx-get="/url"', $result);
    }

    public function testRemoveClassInTemplate(): void
    {
        $result = $this->render(
            "{{ tag('div', {class: 'a b c', remove_class: 'b', html: 'x'}) }}",
        );
        self::assertStringContainsString('class="a c"', $result);
        self::assertStringNotContainsString('remove_class', $result);
    }
}
