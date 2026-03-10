# Twigs

A framework-agnostic Twig extension that bundles useful functions, filters, and tags into a single entry point.

## Installation

```bash
composer require esheep/twigs
```

## Setup

Register the extension with your Twig environment:

```php
$twig->addExtension(new \eSheep\Twigs\Extension());
```

### Constructor Options

```php
use eSheep\Twigs\Extension;
use TalesFromADev\TailwindMerge\TailwindMerge;

$twig->addExtension(new Extension(
    // Optional: inject a pre-configured TailwindMerge instance
    tailwindMerge: new TailwindMerge(additionalConfiguration: [...]),

    // Optional: default options for the merge_attributes filter
    mergeOptions: [
        'remove_duplicate_classes' => true, // default: true
        'tailwind_merge' => true,           // default: true
    ],
));
```

## Functions

### `html_tag` / `tag`

Generates an HTML tag.

```twig
{{ html_tag('div', {class: 'container', id: 'main'}) }}
{# <div id="main" class="container"></div> #}

{{ tag('p', {text: 'Hello <world>'}) }}
{# <p>Hello &lt;world&gt;</p> #}

{{ tag('p', {html: '<strong>Bold</strong>'}) }}
{# <p><strong>Bold</strong></p> #}
```

- `text`: HTML-encodes the value and uses it as the tag body.
- `html`: Uses the value as raw HTML tag body.
- All other keys are rendered as HTML attributes.

### `html_attr` / `attr`

Renders an attribute array as an HTML attribute string.

```twig
<div{{ attr({class: ['a', 'b'], id: 'main', 'data-value': '42'}) }}>
{# <div class="a b" id="main" data-value="42"> #}
```

### `html_cva` / `cva`

Creates a [CVA (Class Variant Authority)](https://cva.style/) instance for managing component class variants.

```twig
{% set button = cva('btn', {
    color: {primary: 'btn-primary', danger: 'btn-danger'},
    size: {sm: 'btn-sm', lg: 'btn-lg'}
}, [], {color: 'primary', size: 'sm'}) %}

<button class="{{ button.apply({size: 'lg'}) }}">
{# <button class="btn btn-primary btn-lg"> #}
```

## Filters

### `html_attr` / `attr`

Modifies the attributes of an existing HTML tag string.

```twig
{{ '<div class="old">'|attr({class: 'new', id: 'test'}) }}
{# <div id="test" class="old new"> #}
```

### `merge_attributes`

Merges two attribute arrays with optional class deduplication and Tailwind class conflict resolution. The result can be passed to `attr()` to render as HTML attributes.

```twig
{% set base = {class: 'p-4 text-red-500', id: 'hero'} %}
{% set extra = {class: 'p-2 font-bold', 'hx-get': '/api'} %}

{# Basic merge — deduplicates classes and resolves Tailwind conflicts by default #}
<div{{ base|merge_attributes(extra)|attr }}>
{# <div id="hero" class="text-red-500 p-2 font-bold" hx-get="/api"> #}

{# Override options per call #}
{% set merged = base|merge_attributes(extra, {tailwind_merge: false}) %}
{% set merged = base|merge_attributes(extra, {remove_duplicate_classes: false}) %}
```

**Options** (third argument, overrides extension defaults):

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `remove_duplicate_classes` | `bool` | `true` | Deduplicate class names after merging |
| `tailwind_merge` | `bool` | `true` | Resolve Tailwind CSS class conflicts |

### `tailwind_merge` / `tw_merge`

Merges Tailwind CSS classes, intelligently resolving conflicts.

```twig
{{ 'px-2 py-1 bg-red-500'|tw_merge('p-4') }}
{# bg-red-500 p-4 #}

{{ 'text-red-500'|tw_merge('text-blue-500') }}
{# text-blue-500 #}
```

## Tags

### `{% html_tag %}` / `{% tag %}`

Block-level tag generation. Captures content between the tags.

```twig
{% tag 'nav' with {class: 'main-nav', role: 'navigation'} %}
    <a href="/">Home</a>
    <a href="/about">About</a>
{% endtag %}

{% html_tag 'section' with {id: 'hero', class: 'full-width'} %}
    <h1>Welcome</h1>
{% endhtml_tag %}
```

## Attribute Normalization

Attributes are normalized automatically:

- **`class`** strings are split into arrays and deduplicated on merge.
- **`style`** strings are parsed into property/value pairs.
- **Data attributes** like `data-foo` are collapsed into `data: {foo: ...}`.
- **`removeClass`** pseudo-attribute removes classes:

```twig
{{ '<div class="a b c">'|attr({removeClass: 'b'}) }}
{# <div class="a c"> #}
```

Supported data attribute prefixes: `aria`, `data`, `data-hx`, `data-ng`, `hx`, `ng`.

## Provenance

Some code in this package is ported from [Craft CMS](https://github.com/craftcms/cms). See [PROVENANCE.md](PROVENANCE.md) for details on what was ported and from which commit.

## License

MIT. See [LICENSE](LICENSE).
