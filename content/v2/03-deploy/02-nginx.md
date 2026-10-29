---
title: nginx
description: Serve cached pages from nginx with PHP-FPM as the fallback.
---

# nginx

A ready-made server block lives in `deploy/nginx.conf`. The key line is the `try_files` chain:

```nginx
location / {
    # 1. real file  2. cached page  3. cached data file  4. PHP
    try_files $uri /cache$uri/index.html /cache$uri /index.php$is_args$args;
}
```

nginx checks for a rendered page first and only hands the request to PHP-FPM when there isn’t one.

## Deploy script

```bash title="deploy.sh"
git pull --ff-only
composer install --no-dev --optimize-autoloader
php bin/inkdocs check
php bin/inkdocs cache:warm
```

`cache:warm` renders every page before the first visitor arrives, so nobody hits a cold page.
