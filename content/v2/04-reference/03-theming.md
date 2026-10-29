---
description: Switch themes, change colors and fonts, and edit templates.
---

# Theming

Inkdocs ships with two looks. Pick one in `config.php`:

```php title="config.php"
'theme' => 'wayan',     // Bold: black and white, heavy uppercase headlines, square corners
// 'theme' => 'neutral', // Neutral: quieter type and rounded corners
```

Both themes support light and dark mode. The reader's system setting is used until they press the sun/moon button; their choice is then remembered.

## Files

| File | Purpose |
| --- | --- |
| `theme/layout.php` | Page shell: top bar, sidebar, article, “On this page”, search dialog |
| `theme/nav.php` | Sidebar tree |
| `public/assets/wyn-tokens.css` | Design tokens: every color, font and border width |
| `public/assets/wyn-inkdocs.css` | Layout and components. Uses the tokens only |
| `public/assets/wyn-inkdocs.js` | Search, light/dark toggle, copy buttons, mobile menu |
| `public/assets/fonts/` | Inter, bundled (SIL Open Font License) |
| `public/assets/vendor/wyn-mermaid.min.js` | Mermaid, loaded only on pages with a diagram |

Nothing loads from Google Fonts or a CDN, so the site works offline and sends no visitor data to third parties.

## Change the colors

Override tokens in a small file of your own, so updates don’t overwrite your changes:

```css title="public/assets/brand.css"
:root {
  --wyn-ink: #0f3d3e;      /* text, rules, buttons */
  --wyn-wash: #eef5f4;     /* soft background panels */
}
```

Then add it to `theme/layout.php` after the other stylesheets:

```php
<link rel="stylesheet" href="<?= $v->asset('brand.css') ?>">
```

Status colors (`--wyn-up`, `--wyn-down`, `--wyn-degraded`, `--wyn-maint`) are used only by callouts, where the color carries meaning.

## Class names

Every class and data attribute in the theme starts with `wyn-` (`.wyn-doc`, `data-wyn-search`), so your own CSS never collides with it. The `hljs-*` classes inside code blocks come from the syntax highlighter and keep its names.

## Variables in templates

| Variable | Type | Notes |
| --- | --- | --- |
| `$page` | `Page` or `null` | `null` on the 404 page |
| `$rendered` | `RenderedPage` | `html`, `toc`, `hasH1`, `hasMermaid` |
| `$repo` | `Repository` | `tree()`, `find($slug)` |
| `$version` | `Version` | `id`, `label`, `latest` |
| `$config` | `Config` | `get('key')` |
| `$v` | `View` | `e()` to escape, `url()`, `asset()` |

> [!CAUTION]
> Always print user-facing strings with `$v->e()`. Only `$rendered->html` is already safe HTML.
