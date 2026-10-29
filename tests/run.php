<?php

declare(strict_types=1);

/*
 * Dependency-free test suite: `php tests/run.php` (or `composer test`).
 * Renders the bundled docs plus a throwaway fixture and checks routing, caching and Markdown output.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Inkdocs\Build\Builder;
use Inkdocs\Cache\PageCache;
use Inkdocs\Config;
use Inkdocs\Http\App;
use Inkdocs\Site;

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? "\033[32m  ✓\033[0m " : "\033[31m  ✗\033[0m ") . $name . ($ok || $detail === '' ? '' : "  ({$detail})") . PHP_EOL;
}

function fixture(array $files): string
{
    $dir = sys_get_temp_dir() . '/inkdocs-test-' . bin2hex(random_bytes(4));
    foreach ($files as $path => $body) {
        PageCache::put($dir . '/' . $path, $body);
    }

    return $dir;
}

// ---------------------------------------------------------------------------
echo "Routing (bundled docs)\n";
$app = new App(new Site(Config::load($root, ['debug' => true])));
$status = fn (string $uri) => $app->handle('GET', $uri)->status;

check('home renders', $status('/') === 200);
check('nested page renders', $status('/getting-started/installation') === 200);
check('trailing slash accepted', $status('/getting-started/installation/') === 200);
check('older version renders', $status('/v1/getting-started/installation') === 200);
check('latest prefix redirects to root', $app->handle('GET', '/v2/reference/cli')->headers['Location'] === '/reference/cli');
check('unknown page is 404', $status('/does-not-exist') === 404);
check('path traversal is 404', $status('/../config.php') === 404 && $status('/%2e%2e/config.php') === 404);
check('POST is rejected', $app->handle('POST', '/')->status === 405);
check('raw markdown served', str_starts_with($app->handle('GET', '/reference/cli.md')->body, '# Command line'));
check('search index is JSON', is_array(json_decode($app->handle('GET', '/search.json')->body, true)));
check('llms.txt lists pages', str_contains($app->handle('GET', '/llms.txt')->body, '/getting-started/installation.md'));
check('sitemap has absolute URLs', str_contains((string) $app->handle('GET', '/sitemap.xml')->body, '<loc>https://'));

// ---------------------------------------------------------------------------
echo "Subfolder install\n";
$sub = new App(new Site(Config::load($root, ['debug' => true, 'base_url' => '/docs/'])));
$html = $sub->handle('GET', '/docs/')->body;
preg_match_all('~href="(/[^"]*)"~', $html, $m);
check('every internal link keeps the /docs/ prefix', array_filter($m[1], fn ($h) => !str_starts_with($h, '/docs/')) === []);
check('request outside base is 404', $sub->handle('GET', '/getting-started/installation')->status === 404);

// ---------------------------------------------------------------------------
echo "Content conventions (fixture)\n";
$dir = fixture([
    'index.md'                 => "---\ntitle: Home\n---\nSee [b](02-guide/01-b.md#two) and [gone](missing.md).\n",
    '02-guide/01-b.md'         => "# Bee\n\n## Two\n\n> [!WARNING]\n> Careful.\n\n```php title=\"x.php\"\necho 1;\n```\n",
    '02-guide/02-a.md'         => "# Ay\n",
    '02-guide/03-secret.md'    => "---\ndraft: true\n---\n# Secret\n",
    '02-guide/04-quiet.md'     => "---\nhidden: true\n---\n# Quiet\n",
    '01-start/index.md'        => "---\ntitle: Start here\n---\nText\n",
    '_private/notes.md'        => "# Notes\n",
]);
$site = new Site(Config::load($root, ['versions' => [], 'content_dir' => $dir]));
$repo = $site->repo($site->config->latest());
$app = new App($site);

check('number prefix stripped from URL', $repo->find('guide/b') !== null);
check('number prefix sets order', array_column(array_slice($repo->tree(), 1), 'title') === ['Start here', 'Guide']);
check('drafts excluded', $repo->find('guide/secret') === null);
check('underscore folders ignored', $repo->find('private/notes') === null);
check('hidden page reachable', $app->handle('GET', '/guide/quiet')->status === 200);
check('hidden page skipped by pager', $repo->find('guide/a')->next === null);
check('title from first heading', $repo->find('guide/b')->title === 'Bee');

$home = $app->handle('GET', '/')->body;
check('.md links rewritten to URLs', str_contains($home, 'href="/guide/b#two"'));
check('broken links flagged', str_contains($home, 'class="wyn-broken-link"') && $site->renderer->brokenLinks() !== []);

$page = $app->handle('GET', '/guide/b')->body;
check('callout rendered', str_contains($page, 'class="wyn-callout wyn-callout-warning"') && !str_contains($page, '[!WARNING]'));
check('code highlighted server-side', str_contains($page, 'hljs-keyword') || str_contains($page, 'hljs-built_in'));
check('code title rendered', str_contains($page, '<div class="wyn-code-title">x.php</div>'));
check('heading ids for TOC', str_contains($page, 'id="two"'));
check('no version switcher when unversioned', !str_contains($page, 'version-select'));

// ---------------------------------------------------------------------------
echo "Design\n";
$bold = (new App(new Site(Config::load($root, ['debug' => true]))))->handle('GET', '/getting-started/installation')->body;
check('bold theme by default', str_contains($bold, 'data-wyn-theme="wayan"'));
check('no outside requests (fonts, CDNs)', !preg_match('~(src|href)="https?://(?!github\.com)[^"]*\.(css|js|woff2?)~', $bold) && !str_contains($bold, 'googleapis') && !str_contains($bold, 'jsdelivr'));
check('page title gets the headline full stop', str_contains($bold, 'Installation<span class="wyn-dot">.</span></h1>'));
check('no double full stop when the title already ends in punctuation', !str_contains((new App(new Site(Config::load($root, ['debug' => true]))))->handle('GET', '/')->body, 'docs.<span class="wyn-dot">'));
$neutral = (new App(new Site(Config::load($root, ['debug' => true, 'theme' => 'neutral']))))->handle('GET', '/')->body;
check('neutral theme from config', str_contains($neutral, 'data-wyn-theme="neutral"'));
$diagram = (new App(new Site(Config::load($root, ['debug' => true]))))->handle('GET', '/getting-started/project-structure')->body;
check('Mermaid loads locally, only on pages with a diagram', str_contains($diagram, 'vendor/wyn-mermaid.min.js') && !str_contains($bold, 'wyn-mermaid.min.js'));

// ---------------------------------------------------------------------------
echo "Build\n";
$out = sys_get_temp_dir() . '/inkdocs-build-' . bin2hex(random_bytes(4));
$stats = (new Builder(new Site(Config::load($root))))->build($out, static: true);
check('static build writes every page', is_file("$out/index.html") && is_file("$out/v1/getting-started/installation/index.html"));
check('static build writes data files', is_file("$out/search.json") && is_file("$out/v1/search.json") && is_file("$out/llms.txt"));
check('static build copies assets', is_file("$out/assets/wyn-inkdocs.css") && is_file("$out/assets/fonts/wyn-inter-latin.woff2") && !is_file("$out/index.php"));
check('static build has 404 page', is_file("$out/404.html"));
check('cache path mapping', PageCache::relativeFile('') === 'index.html'
    && PageCache::relativeFile('a/b') === 'a/b/index.html'
    && PageCache::relativeFile('v1/search.json') === 'v1/search.json');

echo PHP_EOL . ($failed === 0 ? "\033[32m{$passed} passed\033[0m" : "\033[31m{$failed} failed\033[0m, {$passed} passed") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
