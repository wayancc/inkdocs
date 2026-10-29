---
title: Command line
description: bin/inkdocs commands.
---

# Command line

```bash
php bin/inkdocs <command> [options]
```

| Command | What it does |
| --- | --- |
| `serve [--port=8000] [--host=127.0.0.1]` | Dev server with the cache off |
| `build [--out=dist] [--base=/]` | Export a fully static site |
| `cache:warm` | Clear and pre-render every page into `public/cache` |
| `cache:clear` | Empty `public/cache` |
| `check` | Report broken links between pages. Exits with `1` when any are found |
| `new <path> ["Title"]` | Create a page with front matter |

## Use `check` in CI

```yaml title=".github/workflows/ci.yml"
- run: composer install --no-progress
- run: php bin/inkdocs check
```

The included workflow already does this on every pull request.
