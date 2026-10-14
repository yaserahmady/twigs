# Twigs

> [!WARNING]
> **Alpha software, not ready for public use.** Twigs is built for my agency's own projects. Names, behavior and output can change in any release, without notice or upgrade notes.

A framework-agnostic Twig extension that bundles useful functions, filters, and tags into a single entry point.

**Documentation:** https://yaserahmady.github.io/twigs/ (source in [`docs/`](docs/content)). Coding agents can use the [`esheep-twigs` skill](skills/esheep-twigs/SKILL.md).

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
{# <div class="container" id="main"></div> #}

{{ tag('p', {text: 'Hello <world>'}) }}
{# <p>Hello &lt;world&gt;</p> #}

{{ tag('p', 'Hello <world>') }}
{# <p>Hello &lt;world&gt;</p> #}

{{ tag('p', {html: '<strong>Bold</strong>'}) }}
{# <p><strong>Bold</strong></p> #}
```

- `text`: HTML-encodes the value and uses it as the tag body.
- `html`: Uses the value as raw HTML tag body. Takes precedence over `text`.
- All other keys are rendered as HTML attributes.
- Passing a string instead of a hash is a shortcut for `{text: '...'}`.

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
{# <div class="old new" id="test"> #}
```

If the string doesn't contain an HTML tag, it's returned unchanged.

### `html_remove_class` / `remove_class`

Removes a class (or classes) from an existing HTML tag string. If no classes remain, the `class` attribute is removed.

```twig
{{ '<div class="a b c">'|remove_class('b') }}
{# <div class="a c"> #}

{{ '<button class="btn btn-primary active">Save</button>'|remove_class(['btn-primary', 'active']) }}
{# <button class="btn">Save</button> #}
```

If the string doesn't contain an HTML tag, it's returned unchanged.

### `merge_attributes`

Merges two attribute arrays with optional class deduplication and Tailwind class conflict resolution. The result can be passed to `attr()` to render as HTML attributes.

```twig
{% set base = {class: 'p-4 text-red-500', id: 'hero'} %}
{% set extra = {class: 'p-2 font-bold', 'hx-get': '/api'} %}

{# Basic merge — deduplicates classes and resolves Tailwind conflicts by default #}
<div{{ attr(base|merge_attributes(extra)) }}>
{# <div class="text-red-500 p-2 font-bold" id="hero" hx-get="/api"> #}

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

Block-level tag generation. Captures content between the tags. Each tag closes with its own end tag: `{% tag %}` with `{% endtag %}`, `{% html_tag %}` with `{% endhtml_tag %}`.

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
- **`remove_class`** pseudo-attribute removes classes from the `class` value in the same hash:

```twig
{{ tag('div', {class: 'a b c', remove_class: 'b'}) }}
{# <div class="a c"></div> #}
```

It doesn't remove classes already on a tag passed to `|attr`. Use the [`remove_class`](#html_remove_class--remove_class) filter for that.

Supported data attribute prefixes: `aria`, `data`, `data-hx`, `data-ng`, `hx`, `ng`.

## Credits

The tag and attribute helpers are ported from [Craft CMS](https://github.com/craftcms/cms).

## License

MIT. See [LICENSE](LICENSE).
