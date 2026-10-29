<?php

declare(strict_types=1);

namespace Inkdocs\Http;

use Inkdocs\Cache\PageCache;
use Inkdocs\Site;

/**
 * Maps a request path to output. Only runs on a cache miss.
 *
 *   /                      latest home         /v1/guide/install     older version
 *   /guide/install         page                /guide/install.md     raw Markdown
 *   /search.json           search index        /v1/search.json
 *   /sitemap.xml  /llms.txt  /llms-full.txt
 */
final class App
{
    private const TYPES = [
        'html' => 'text/html; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'xml'  => 'application/xml; charset=utf-8',
        'txt'  => 'text/plain; charset=utf-8',
        'md'   => 'text/markdown; charset=utf-8',
    ];

    public function __construct(private readonly Site $site)
    {
    }

    public function handle(string $method, string $uri): Response
    {
        if (!in_array($method, ['GET', 'HEAD'], true)) {
            return new Response(405, 'Method not allowed', self::TYPES['txt'], ['Allow' => 'GET, HEAD']);
        }

        $config = $this->site->config;
        $base = $config->baseUrl();
        $path = rawurldecode((string) parse_url($uri, PHP_URL_PATH));

        if (!str_starts_with(rtrim($path, '/') . '/', $base)) {
            return $this->notFound();
        }

        $rel = trim(substr($path, strlen($base) - 1), '/');
        if ($rel !== '' && (!preg_match('~^[A-Za-z0-9._/-]+$~', $rel) || str_contains($rel, '..'))) {
            return $this->notFound();
        }

        // Site-wide files (latest version)
        $global = match ($rel) {
            'sitemap.xml'   => fn () => $this->site->sitemap(),
            'llms.txt'      => fn () => $config->get('llms_txt') ? $this->site->llmsTxt() : null,
            'llms-full.txt' => fn () => $config->get('llms_txt') ? $this->site->llmsFullTxt() : null,
            default         => null,
        };
        if ($global !== null) {
            $body = $global();

            return $body === null ? $this->notFound() : $this->ok($rel, $body);
        }

        // Pick the version from the first segment
        $version = $config->latest();
        $rest = $rel;
        $first = explode('/', $rel, 2)[0];
        foreach ($config->versions() as $v) {
            if ($v->id !== '' && $v->id === $first) {
                if ($v->latest) {
                    // /v2/guide → /guide : the latest version lives at the root
                    $target = $base . ltrim(substr($rel, strlen($first)), '/');

                    return new Response(301, '', self::TYPES['txt'], ['Location' => $target]);
                }
                $version = $v;
                $rest = trim(substr($rel, strlen($first)), '/');
                break;
            }
        }

        $repo = $this->site->repo($version);

        if ($rest === 'search.json') {
            return $this->ok($rel, $this->site->searchIndex($version));
        }

        if (str_ends_with($rest, '.md')) {
            $slug = substr($rest, 0, -3);
            $page = $repo->find($slug === 'index' ? '' : $slug);

            return $page ? $this->ok($rel, $this->site->raw($page)) : $this->notFound($version);
        }

        $page = $repo->find($rest);
        if ($page === null) {
            return $this->notFound($version);
        }

        return $this->ok($rel, $this->site->renderPage($page));
    }

    private function ok(string $rel, string $body): Response
    {
        $ext = pathinfo(PageCache::relativeFile($rel), PATHINFO_EXTENSION);

        return new Response(200, $body, self::TYPES[$ext] ?? self::TYPES['html'], [], cacheKey: $rel);
    }

    private function notFound(?\Inkdocs\Version $version = null): Response
    {
        $html = $this->site->renderNotFound($version ?? $this->site->config->latest());

        return new Response(404, $html, self::TYPES['html']);
    }
}
