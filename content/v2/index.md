---
title: Introduction
description: A fast, flat-file documentation engine for PHP 8.
---

# Write Markdown. Ship docs.

Inkdocs turns a folder of Markdown files into a fast documentation site. There’s no database, no Node build step and no admin panel to secure. Drop it on any PHP 8.2+ host, or export plain HTML for GitHub Pages.

```text
content/
├── index.md                    → /
├── 01-getting-started/
│   ├── 01-installation.md      → /getting-started/installation
│   └── 02-quick-start.md       → /getting-started/quick-start
└── 02-guides/
    └── deploying.md            → /guides/deploying
```

## Why Inkdocs

**It’s fast where it counts.** PHP renders each page once and writes plain HTML to `public/cache`. After that, Apache or nginx serves the file directly. PHP never starts, so a $3 shared host answers like a CDN.

**Your docs stay portable.** Pages are plain GitHub-flavored Markdown. Links between pages point at `.md` files, so the same docs read correctly on GitHub, in your editor, and on the site.

**Docs features are built in.**

- Sidebar generated from your folders, with ordering by number prefix
- Versioned docs with a version switcher
- Instant search, with no server or third-party service
- “On this page” table of contents
- Server-side syntax highlighting (zero highlighting JavaScript)
- Two themes (bold and neutral), light and dark mode, no outside requests
- GitHub-style callouts, Mermaid diagrams, footnotes, tables
- Broken-link checker for CI
- `llms.txt` and raw `.md` for every page, so AI tools can read your docs

> [!TIP]
> This site is the Inkdocs documentation, built with Inkdocs. Every page lives in `content/v2/`.

## Next steps

- [Install Inkdocs](01-getting-started/01-installation.md)
- [Write your first page](01-getting-started/02-quick-start.md)
- [Deploy to shared hosting](03-deploy/01-shared-hosting.md)
