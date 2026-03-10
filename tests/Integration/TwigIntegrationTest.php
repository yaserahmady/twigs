<?php

declare(strict_types=1);

namespace eSheep\Twigs\Tests\Integration;

use eSheep\Twigs\Extension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
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
            "{{ tag('div', {class: 'a b c', removeClass: 'b', html: 'x'}) }}",
        );
        self::assertStringContainsString('class="a c"', $result);
        self::assertStringNotContainsString('removeClass', $result);
    }
}
