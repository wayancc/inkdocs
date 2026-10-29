---
title: Shared hosting (Apache)
nav_title: Apache & shared hosting
description: Deploy to cPanel, Plesk, or any Apache or LiteSpeed host.
---

# Shared hosting (Apache)

Inkdocs runs on any host with PHP 8.2+. The included `public/.htaccess` makes Apache serve cached pages directly.

## Steps

1. Upload the project, including `vendor/`. Run `composer install --no-dev` first if you built it yourself.
2. Set the domain’s document root to the `public/` folder.
3. Make `public/cache/` writable by PHP.
4. Visit the site. Each page is rendered once, then served from cache.

> [!TIP]
> If your host won’t let you change the document root, upload the contents of `public/` into `public_html/`, put everything else one level up, and change `$root` at the top of `index.php` to point at it.

## Updating content

After changing Markdown on the server, rebuild the cache:

```bash
php bin/inkdocs cache:warm
```

No SSH? Use the host’s cron or terminal to run the same command, or delete the `public/cache/` folder. Pages regenerate on the next visit.

> [!WARNING]
> With `cache` on, edits don’t appear until the cache is rebuilt. While writing directly on a live server, set `'cache' => false` in `config.php`.

## Check that the cache is used

```bash
curl -sI https://docs.example.com/getting-started/installation
```

A page served by Apache from cache has no `X-Inkdocs-Cache` header. One served by PHP shows `HIT` (PHP fast path) or `MISS` (just rendered).
