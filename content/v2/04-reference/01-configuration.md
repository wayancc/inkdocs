---
description: Every option in config.php.
---

# Configuration

`config.php` returns an array. Every key is optional.

| Key | Default | What it does |
| --- | --- | --- |
| `site_name` | `'Inkdocs'` | Name in the top bar and page titles |
| `description` | `''` | Default meta description; first line of `llms.txt` |
| `site_url` | `''` | Full origin, e.g. `https://docs.acme.dev`. Needed for `sitemap.xml` and canonical links |
| `base_url` | `'/'` | Path prefix for a subfolder install, e.g. `'/docs/'` |
| `versions` | `[]` | `['v2' => 'v2.x', 'v1' => 'v1.x']`. First is latest |
| `edit_url` | `''` | “Edit this page” link. `{path}` is the file path inside `content/` |
| `nav_links` | `[]` | Links in the top bar, `['GitHub' => 'https://…']` |
| `footer` | `''` | HTML shown at the bottom of every page |
| `cache` | `true` | Write rendered pages to `public/cache` |
| `debug` | `false` | Show drafts and never cache |
| `allow_html` | `true` | Allow raw HTML in Markdown. Set `false` for untrusted authors |
| `highlight` | `true` | Server-side syntax highlighting |
| `theme` | `'wayan'` | `'wayan'` (bold, black and white) or `'neutral'` (quieter, rounded). See [Theming](03-theming.md) |
| `llms_txt` | `true` | Serve `/llms.txt` and `/llms-full.txt` |
| `lang` | `'en'` | `<html lang>` attribute |
| `content_dir` | `'content'` | Where the Markdown lives |
| `theme_dir` | `'theme'` | Where the templates live |

## Environment

Setting `INKDOCS_DEBUG=1` turns on debug mode without editing the file. `bin/inkdocs serve` does this for you.
