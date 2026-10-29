<?php

declare(strict_types=1);

namespace Inkdocs\Content;

use Inkdocs\Version;
use Symfony\Component\Yaml\Yaml;

/**
 * Scans one version folder and turns the file tree into pages + a sidebar tree.
 *
 * Conventions
 *  - Folders become sidebar sections; a folder's index.md is the section's landing page.
 *  - Numeric prefixes set the order and are stripped from URLs: 02-guide/01-install.md → /guide/install
 *  - Front matter `order:` overrides the prefix, `hidden: true` keeps a page out of the sidebar,
 *    `draft: true` excludes it entirely (unless debug mode is on).
 *  - Files and folders starting with "_" or "." are ignored.
 */
final class Repository
{
    /** @var array<string, Page> slug => page, in reading order */
    private array $pages = [];

    /** @var array<string, Page> relPath => page */
    private array $bySource = [];

    /** @var list<array> */
    private array $tree = [];

    public function __construct(
        public readonly Version $version,
        private readonly bool $includeDrafts = false,
    ) {
        if (!is_dir($version->dir)) {
            throw new \RuntimeException("Content folder not found: {$version->dir}");
        }

        $this->tree = $this->scan($version->dir, '', '', []);
        $this->flatten($this->tree);
        $this->link();
    }

    public function find(string $slug): ?Page
    {
        return $this->pages[trim($slug, '/')] ?? null;
    }

    public function findBySource(string $relPath): ?Page
    {
        return $this->bySource[$relPath] ?? null;
    }

    /** @return array<string, Page> */
    public function pages(): array
    {
        return $this->pages;
    }

    /** @return list<array> */
    public function tree(): array
    {
        return $this->tree;
    }

    private function scan(string $dir, string $relDir, string $slugDir, array $crumbs): array
    {
        $nodes = [];
        $entries = scandir($dir) ?: [];

        foreach ($entries as $name) {
            if ($name[0] === '.' || $name[0] === '_') {
                continue;
            }

            $abs = $dir . '/' . $name;
            $rel = ltrim($relDir . '/' . $name, '/');

            if (is_dir($abs)) {
                $slug = ltrim($slugDir . '/' . self::slugify($name), '/');
                $index = is_file($abs . '/index.md') ? $this->makePage($abs . '/index.md', $rel . '/index.md', $slug) : null;
                if ($index === null && !$this->hasMarkdown($abs)) {
                    continue;
                }

                $title = $index?->title ?? self::humanize($name);
                $childCrumbs = [...$crumbs, ['title' => $title, 'slug' => $slug]];
                if ($index) {
                    $index->breadcrumbs = $crumbs;
                }

                $nodes[] = [
                    'type'     => 'section',
                    'title'    => $index?->meta['nav_title'] ?? $title,
                    'slug'     => $slug,
                    'page'     => $index,
                    'order'    => $index?->meta['order'] ?? self::prefixOrder($name),
                    'hidden'   => $index?->hidden ?? false,
                    'children' => $this->scan($abs, $rel, $slug, $childCrumbs),
                ];
                continue;
            }

            if (!str_ends_with($name, '.md')) {
                continue;
            }

            $base = substr($name, 0, -3);
            $isIndex = strtolower($base) === 'index';

            if ($isIndex && $relDir !== '') {
                continue; // handled as the section landing page above
            }

            $slug = $isIndex ? '' : ltrim($slugDir . '/' . self::slugify($base), '/');
            $page = $this->makePage($abs, $rel, $slug);
            if ($page === null) {
                continue;
            }
            $page->breadcrumbs = $crumbs;

            $nodes[] = [
                'type'   => 'page',
                'title'  => $page->meta['nav_title'] ?? $page->title,
                'slug'   => $slug,
                'page'   => $page,
                'order'  => $isIndex ? -PHP_INT_MAX : $page->order,
                'hidden' => $page->hidden,
            ];
        }

        usort($nodes, static fn (array $a, array $b) =>
            [(int) $a['order'], $a['title']] <=> [(int) $b['order'], $b['title']]);

        return $nodes;
    }

    private function makePage(string $abs, string $rel, string $slug): ?Page
    {
        [$meta, $body] = self::splitFrontMatter((string) file_get_contents($abs));

        if (!empty($meta['draft']) && !$this->includeDrafts) {
            return null;
        }

        $title = $meta['title'] ?? self::firstHeading($body);
        if ($title === null) {
            $file = basename($rel, '.md');
            $dir = dirname($rel);
            $title = strtolower($file) !== 'index' ? self::humanize($file) : ($dir === '.' ? 'Home' : self::humanize(basename($dir)));
        }

        $page = new Page(
            version: $this->version,
            source: $abs,
            relPath: $rel,
            slug: $slug,
            title: (string) $title,
            description: (string) ($meta['description'] ?? ''),
            order: (int) ($meta['order'] ?? self::prefixOrder(basename($rel, '.md'))),
            hidden: !empty($meta['hidden']),
            meta: $meta,
            body: $body,
        );

        $this->bySource[$rel] = $page;

        return $page;
    }

    private function flatten(array $nodes): void
    {
        foreach ($nodes as $node) {
            if ($node['page'] instanceof Page) {
                $this->pages[$node['page']->slug] = $node['page'];
            }
            if ($node['type'] === 'section') {
                $this->flatten($node['children']);
            }
        }
    }

    private function link(): void
    {
        $visible = array_values(array_filter($this->pages, static fn (Page $p) => !$p->hidden));
        foreach ($visible as $i => $page) {
            $page->prev = $visible[$i - 1] ?? null;
            $page->next = $visible[$i + 1] ?? null;
        }
    }

    private function hasMarkdown(string $dir): bool
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (str_ends_with($file->getFilename(), '.md')) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: array, 1: string} */
    public static function splitFrontMatter(string $raw): array
    {
        $raw = ltrim($raw, "\xEF\xBB\xBF"); // UTF-8 BOM
        if (preg_match('/\A---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/s', $raw, $m)) {
            $meta = Yaml::parse($m[1]);

            return [is_array($meta) ? $meta : [], $m[2]];
        }

        return [[], $raw];
    }

    private static function firstHeading(string $body): ?string
    {
        // Skip fenced code so a "# comment" in a shell snippet is never taken as the title.
        $clean = preg_replace('/^(```|~~~).*?^\1/ms', '', $body) ?? $body;

        return preg_match('/^#\s+(.+?)\s*#*\s*$/m', $clean, $m) ? trim($m[1]) : null;
    }

    public static function slugify(string $name): string
    {
        $name = preg_replace('/^\d+[-_.\s]+/', '', $name) ?? $name;
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?? $name;

        return trim($name, '-');
    }

    public static function humanize(string $name): string
    {
        $name = preg_replace('/^\d+[-_.\s]+/', '', $name) ?? $name;

        return ucfirst(trim(str_replace(['-', '_'], ' ', $name)));
    }

    private static function prefixOrder(string $name): int
    {
        return preg_match('/^(\d+)[-_.\s]/', $name, $m) ? (int) $m[1] : 1000;
    }
}
