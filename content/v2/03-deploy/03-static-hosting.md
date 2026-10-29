---
title: Static hosting
description: Export plain HTML for GitHub Pages, Netlify, Cloudflare Pages or S3.
---

# Static hosting

No PHP on the server? Export the whole site as HTML:

```bash
php bin/inkdocs build --out=dist
```

`dist/` contains every page, the search index, `sitemap.xml`, `llms.txt`, a `404.html` and the assets. Upload it anywhere that serves files.

## GitHub Pages

A project site lives under `/<repo>/`, so set the base path:

```bash
php bin/inkdocs build --out=dist --base=/my-docs/
```

The repo includes `.github/workflows/pages.yml`, which builds and publishes on every push to `main`. Turn it on under **Settings → Pages → Source: GitHub Actions**.

## What changes compared to PHP hosting

| | PHP hosting | Static export |
| --- | --- | --- |
| Rebuild after edits | `cache:warm`, or automatic on next visit | Re-run `build` |
| Raw `.md` per page | Yes | Yes |
| Search | Yes | Yes |
| Pretty 404 page | Yes | Yes, if the host uses `404.html` |
