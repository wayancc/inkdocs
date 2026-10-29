<?php

declare(strict_types=1);

namespace Inkdocs\Build;

use Inkdocs\Cache\PageCache;
use Inkdocs\Site;

/**
 * Renders every page to disk.
 *  - into public/cache → warms the cache for PHP hosting
 *  - into dist/        → a fully static site (GitHub Pages, Netlify, S3, any CDN)
 */
final class Builder
{
    /** @var callable(string): void */
    private $log;

    public function __construct(private readonly Site $site, ?callable $log = null)
    {
        $this->log = $log ?? static fn (string $line) => null;
    }

    /** @return array{pages: int, files: int, seconds: float} */
    public function build(string $outDir, bool $static): array
    {
        $start = microtime(true);
        $pages = 0;
        $files = 0;
        $put = function (string $rel, ?string $contents) use ($outDir, &$files): void {
            if ($contents === null) {
                return;
            }
            PageCache::put($outDir . '/' . $rel, $contents);
            $files++;
        };

        foreach ($this->site->config->versions() as $version) {
            $repo = $this->site->repo($version);
            foreach ($repo->pages() as $page) {
                $put(PageCache::relativeFile($page->path()), $this->site->renderPage($page));
                $put($page->rawPath(), $this->site->raw($page));
                $pages++;
                ($this->log)('  ' . ($page->url($this->site->config->baseUrl())));
            }
            $put($version->prefix . 'search.json', $this->site->searchIndex($version));
        }

        $put('sitemap.xml', $this->site->sitemap());
        if ($this->site->config->get('llms_txt')) {
            $put('llms.txt', $this->site->llmsTxt());
            $put('llms-full.txt', $this->site->llmsFullTxt());
        }

        if ($static) {
            $put('404.html', $this->site->renderNotFound($this->site->config->latest()));
            $put('.nojekyll', '');
            $files += $this->copyPublic($this->site->config->path('public_dir'), $outDir);
        }

        return ['pages' => $pages, 'files' => $files, 'seconds' => microtime(true) - $start];
    }

    /** Copy assets and other public files, skipping the PHP entry point and the cache. */
    private function copyPublic(string $from, string $to): int
    {
        $skip = ['index.php', '.htaccess', 'cache'];
        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($from) + 1);
            if (in_array(explode('/', $rel)[0], $skip, true)) {
                continue;
            }
            $dest = $to . '/' . $rel;
            if ($file->isDir()) {
                is_dir($dest) || mkdir($dest, 0775, true);
            } else {
                copy($file->getPathname(), $dest);
                $count++;
            }
        }

        return $count;
    }
}
