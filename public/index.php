<?php

declare(strict_types=1);

/*
 * Inkdocs front controller.
 *
 * Request flow (fastest first):
 *   1. Web server finds public/cache/<path>/index.html and serves it — PHP never starts (see .htaccess / nginx.conf).
 *   2. If the server can't do that, this file serves the cached copy before loading anything else.
 *   3. Cache miss: boot the app, render the Markdown, write the cache, respond.
 */

$root = dirname(__DIR__);

// PHP built-in dev server: let it serve real files (CSS, JS, images) directly.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

$config = is_file($root . '/config.php') ? require $root . '/config.php' : [];
$useCache = ($config['cache'] ?? true) && !($config['debug'] ?? false) && !getenv('INKDOCS_DEBUG');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ---- 2. Fast path: no autoloader, no Markdown, just a file read -------------------
if ($useCache && ($method === 'GET' || $method === 'HEAD')) {
    $base = rtrim('/' . trim($config['base_url'] ?? '/', '/'), '/') . '/';
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

    if (str_starts_with(rtrim($path, '/') . '/', $base)) {
        $rel = trim(substr($path, strlen($base) - 1), '/');

        if ($rel === '' || (preg_match('~^[A-Za-z0-9._/-]+$~', $rel) && !str_contains($rel, '..'))) {
            $isFile = preg_match('/\.(json|xml|txt|md)$/', $rel, $ext) === 1;
            $file = __DIR__ . '/cache/' . ($isFile ? $rel : ltrim($rel . '/index.html', '/'));

            if (is_file($file)) {
                $types = ['json' => 'application/json', 'xml' => 'application/xml', 'txt' => 'text/plain', 'md' => 'text/markdown'];
                header('Content-Type: ' . ($isFile ? $types[$ext[1]] : 'text/html') . '; charset=utf-8');
                header('X-Inkdocs-Cache: HIT');
                if ($method === 'GET') {
                    readfile($file);
                }

                return;
            }
        }
    }
}

// ---- 3. Cache miss: render -----------------------------------------------------------
require $root . '/vendor/autoload.php';

$site = new Inkdocs\Site(Inkdocs\Config::load($root));
$response = (new Inkdocs\Http\App($site))->handle($method, $_SERVER['REQUEST_URI'] ?? '/');

if ($useCache && $response->status === 200 && $response->cacheKey !== null) {
    (new Inkdocs\Cache\PageCache(__DIR__ . '/cache'))->write($response->cacheKey, $response->body);
}

header('X-Inkdocs-Cache: MISS');
$response->send($method === 'HEAD');
