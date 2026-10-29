---
description: Requirements and the two ways to install.
---

# Installation

## Requirements

| Requirement | Version | Notes |
| --- | --- | --- |
| PHP | 8.2 or newer | `mbstring` extension (enabled on almost every host) |
| Composer | 2.x | Only on your machine or CI. Not needed on the server if you upload `vendor/` |
| Web server | Apache, LiteSpeed, nginx, Caddy | Or none at all with [static export](../03-deploy/03-static-hosting.md) |

## Install with Composer

```bash
composer create-project wayancc/inkdocs my-docs
cd my-docs
php bin/inkdocs serve
```

Open `http://127.0.0.1:8000`. You’ll see this documentation, which is the starter content.

## Install from a release zip

Every release has a zip that already includes the `vendor/` folder. Use it when your host has no SSH or Composer.

1. Download `inkdocs-x.y.z.zip` from the GitHub releases page.
2. Upload and unzip it on your host.
3. Point your domain’s document root at the `public/` folder.

> [!IMPORTANT]
> Only `public/` should be reachable from the web. Your content, config and `vendor/` stay one level above it.

## Check the install

```bash
php bin/inkdocs check
```

This renders every page and reports links that point at missing files. It should print `no broken links`.
