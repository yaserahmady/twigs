---
name: esheep-twigs
description: Use when writing or reviewing Twig templates in a PHP project that uses eSheep Twigs (Composer package esheep/twigs, class eSheep\Twigs\Extension), or when setting it up. Covers the tag() and attr() functions, the {% tag %} / {% html_tag %} block, the |attr, |remove_class, |merge_attributes and |tailwind_merge (|tw_merge) filters, cva() class variants, attribute hashes (class, style, data/aria/hx prefixes, remove_class), building reusable Twig components, and Tailwind CSS class merging in Twig.
---

# eSheep Twigs

eSheep Twigs (`esheep/twigs`) is one Twig extension with helpers for building HTML in templates: tag and attribute builders, attribute merging for components, class variants (CVA) and Tailwind class merging. It works with any `Twig\Environment` on PHP 8.1+ and Twig 3.13+.

## Setup

```bash
composer require esheep/twigs
```

```php
$twig->addExtension(new \eSheep\Twigs\Extension());
```

In Symfony, with autoconfiguration on, register the class as a service: `eSheep\Twigs\Extension: ~` under `services:` in `config/services.yaml`.

Constructor options, both optional and named: `tailwindMerge` (a `TalesFromADev\TailwindMerge\TailwindMergeInterface`) and `mergeOptions` (defaults for `|merge_attributes`). See [Installation](https://yaserahmady.github.io/twigs/installation.md) and [Configuration](https://yaserahmady.github.io/twigs/configuration.md).

## Core concepts

- **Attribute hashes.** Every helper takes attributes as a Twig hash. `true` renders a bare attribute, `false`/`null` omit it, `class` takes a string or list, `style` takes a CSS string or property hash, and `data`/`aria`/`hx`/`data-hx`/`ng`/`data-ng` take nested hashes that expand to prefixed attributes. Attributes render in the order written. [Attribute hashes](https://yaserahmady.github.io/twigs/guides/attributes.md)
- **`remove_class` pseudo-attribute.** Removes classes from the `class` in the same hash only. [Attribute hashes](https://yaserahmady.github.io/twigs/guides/attributes.md)
- **Short and long names.** Each helper has a short name and an `html_*`/`tailwind_*` alias: `tag`/`html_tag`, `attr`/`html_attr`, `cva`/`html_cva`, `|remove_class`/`|html_remove_class`, `|tw_merge`/`|tailwind_merge`. [Reference](https://yaserahmady.github.io/twigs/reference/functions.md)

## Common tasks

### Build an element

```twig
{{ tag('p', 'Hello <world>') }}
{# <p>Hello &lt;world&gt;</p> #}

{{ tag('a', {href: '/docs', class: ['link', 'link-primary'], text: 'Docs'}) }}
{# <a href="/docs" class="link link-primary">Docs</a> #}
```

`text` is HTML-encoded and `html` is inserted raw. `html` wins when both are set. Never put user input in `html`. [Functions](https://yaserahmady.github.io/twigs/reference/functions.md)

### Wrap template content in an element

```twig
{% tag 'nav' with {class: 'main-nav', aria: {label: 'Main'}} %}
    <a href="/">Home</a>
{% endtag %}
```

`{% html_tag %}` closes with `{% endhtml_tag %}`. [Tags](https://yaserahmady.github.io/twigs/reference/tags.md)

### Render attributes in hand-written markup

```twig
<input{{ attr({type: 'checkbox', name: 'agree', checked: true, disabled: false}) }}>
{# <input type="checkbox" name="agree" checked> #}
```

The output starts with a space, so write `attr()` right after the tag name.

### Change an existing HTML string

```twig
{{ '<div class="old">'|attr({class: 'new', id: 'test'}) }}
{# <div class="old new" id="test"> #}

{{ '<div class="a b c">'|remove_class('b') }}
{# <div class="a c"> #}
```

Both act on the first tag only, and return a string without a tag unchanged. [Filters](https://yaserahmady.github.io/twigs/reference/filters.md)

### Build a reusable component

Define variants with `cva()`, merge the caller's attributes with `|merge_attributes`, and render with `{% tag %}`:

```twig
{% set button = cva(
    base: 'inline-flex items-center rounded-md font-medium',
    variants: {
        variant: {primary: 'bg-blue-600 text-white', ghost: 'bg-transparent text-blue-600'},
        size: {sm: 'px-2 py-1 text-sm', md: 'px-4 py-2'},
    },
    defaultVariant: {variant: 'primary', size: 'md'},
) %}

{% set attrs = {
    type: 'button',
    class: button.apply({variant: variant ?? null, size: size ?? null}),
}|merge_attributes(attributes ?? {}) %}

{% tag 'button' with attrs %}{{ label }}{% endtag %}
```

The caller's classes come last, so Tailwind merging lets them win conflicts (`px-6` replaces `px-2`). [Building components](https://yaserahmady.github.io/twigs/guides/components.md)

### Merge Tailwind classes

```twig
{{ 'px-2 py-1 bg-red-500'|tw_merge('p-4') }}
{# bg-red-500 p-4 #}
```

## Gotchas

- **Same-named helpers from other extensions don't apply.** twig/html-extra's `HtmlExtension` (auto-registered by Symfony's twig/extra-bundle) also defines `html_attr()` and `html_cva()`, but this package's functions and filters always win, whatever the registration order. Expect this package's behavior from every name listed here.
- **`|merge_attributes` returns a hash, not a string.** Render it with `attr()`, `tag()` or `{% tag %}`. Its `class` is a list, so `{{ merged.class }}` prints `Array` with a PHP warning. Use `merged.class|join(' ')`.
- **`remove_class` doesn't reach across.** As a hash key, it doesn't remove classes already on a tag given to `|attr`, or classes from the other side of `|merge_attributes`. Use the `|remove_class` filter on an existing tag.
- **`text` and `html` aren't special in `{% tag %}`.** In the block form they render as literal attributes. The block body is the content.
- **Void elements drop content.** `img`, `input`, `br` and the other void elements render without content or a closing tag.
- **Tailwind merging is on by default** in `|merge_attributes`. In a project without Tailwind, pass `{tailwind_merge: false}` as the third argument or set `mergeOptions: ['tailwind_merge' => false]`, or classes that look like Tailwind utilities can be dropped.
- **`ray()` outputs nothing, `|ray` passes through.** Both send values to Spatie Ray, or to PHP's error log. `{{ ray(x) }}` prints nothing, while `{{ x|ray }}` prints `x` as if the filter weren't there.
- **An empty string renders as a bare attribute** (`alt: ''` gives `alt`), which HTML treats the same as `alt=""`.

## Where to look

- [Introduction](https://yaserahmady.github.io/twigs/index.md): what the package provides.
- [Attribute hashes](https://yaserahmady.github.io/twigs/guides/attributes.md): how every kind of attribute value renders.
- [Functions](https://yaserahmady.github.io/twigs/reference/functions.md), [Filters](https://yaserahmady.github.io/twigs/reference/filters.md), [Tags](https://yaserahmady.github.io/twigs/reference/tags.md): signatures, merge rules and options.
- [llms.txt](https://yaserahmady.github.io/twigs/llms.txt): every page with a one-line summary.
