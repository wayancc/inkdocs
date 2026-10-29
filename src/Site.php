<?php

declare(strict_types=1);

namespace Inkdocs;

use Inkdocs\Content\Page;
use Inkdocs\Content\Repository;
use Inkdocs\Markdown\Renderer;
use Inkdocs\View\View;

/**
 * Everything the site can output: pages, the 404 page, search index, sitemap and llms.txt.
 * Used by the HTTP front controller and by the static builder alike.
 */
final class Site
{
    public readonly Renderer $renderer;
    private View $view;

    /** @var array<string, Repository> */
    private array $repos = [];

    public function __construct(public readonly Config $config)
    {
        $this->renderer = new Renderer((bool) $config->get('allow_html'), (bool) $config->get('highlight'));
        $this->view = new View($config);
    }

    public function repo(Version $version): Repository
    {
        return $this->repos[$version->id] ??= new Repository($version, (bool) $this->config->get('debug'));
    }

    public function renderPage(Page $page): string
    {
        $base = $this->config->baseUrl();
        $repo = $this->repo($page->version);
        $rendered = $this->renderer->render($page, $repo, $base);

        return $this->view->render('layout', [
            'page'       => $page,
            'rendered'   => $rendered,
            'repo'       => $repo,
            'version'    => $page->version,
            'versions'   => $this->versionLinks($page),
            'updated'    => $this->updatedAt($page),
            'editUrl'    => $this->editUrl($page),
            'notFound'   => false,
        ]);
    }

    public function renderNotFound(Version $version): string
    {
        return $this->view->render('layout', [
            'page'     => null,
            'rendered' => null,
            'repo'     => $this->repo($version),
            'version'  => $version,
            'versions' => $this->versionLinks(null, $version),
            'updated'  => null,
            'editUrl'  => null,
            'notFound' => true,
        ]);
    }

    /** Markdown source for /page.md — handy for readers, LLMs and "copy page" buttons. */
    public function raw(Page $page): string
    {
        $body = ltrim($page->body);
        if (!preg_match('/^#\s/', $body)) {
            $body = '# ' . $page->title . "\n\n" . $body;
        }

        return $body;
    }

    public function searchIndex(Version $version): string
    {
        $base = $this->config->baseUrl();
        $repo = $this->repo($version);
        $entries = [];

        foreach ($repo->pages() as $page) {
            if (!empty($page->meta['search_exclude'])) {
                continue;
            }
            $rendered = $this->renderer->render($page, $repo, $base);
            $trail = implode(' / ', array_column($page->breadcrumbs, 'title'));

            foreach ($rendered->sections as $section) {
                $entries[] = [
                    't' => $page->title,
                    'h' => $section['heading'],
                    'u' => $page->url($base) . ($section['id'] !== '' ? '#' . $section['id'] : ''),
                    'p' => $trail,
                    'x' => $section['text'],
                ];
            }
        }

        return json_encode($entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function sitemap(): ?string
    {
        $origin = rtrim((string) $this->config->get('site_url'), '/');
        if ($origin === '') {
            return null;
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->config->versions() as $version) {
            foreach ($this->repo($version)->pages() as $page) {
                if ($page->hidden) {
                    continue;
                }
                $xml .= '  <url><loc>' . htmlspecialchars($origin . $page->url($this->config->baseUrl())) . '</loc>'
                    . '<lastmod>' . date('Y-m-d', $page->updatedAt()) . '</lastmod>'
                    . '<priority>' . ($version->latest ? '0.8' : '0.3') . "</priority></url>\n";
            }
        }

        return $xml . "</urlset>\n";
    }

    /** https://llmstxt.org — an index of the latest docs for AI tools. */
    public function llmsTxt(): string
    {
        $origin = rtrim((string) $this->config->get('site_url'), '/');
        $base = $this->config->baseUrl();
        $out = '# ' . $this->config->get('site_name') . "\n\n";
        if ($desc = (string) $this->config->get('description')) {
            $out .= '> ' . $desc . "\n\n";
        }

        $walk = function (array $nodes, int $depth) use (&$walk, &$out, $origin, $base): void {
            foreach ($nodes as $node) {
                if ($node['hidden']) {
                    continue;
                }
                $page = $node['page'];
                if ($node['type'] === 'section' && $depth === 0) {
                    $out .= "\n## " . $node['title'] . "\n\n";
                }
                if ($page instanceof Page) {
                    $md = $origin . $base . $page->rawPath();
                    $out .= '- [' . $page->title . '](' . $md . ')' . ($page->description !== '' ? ': ' . $page->description : '') . "\n";
                }
                if ($node['type'] === 'section') {
                    $walk($node['children'], $depth + 1);
                }
            }
        };
        $walk($this->repo($this->config->latest())->tree(), 0);

        return $out;
    }

    public function llmsFullTxt(): string
    {
        $out = '';
        foreach ($this->repo($this->config->latest())->pages() as $page) {
            $out .= $this->raw($page) . "\n\n---\n\n";
        }

        return $out;
    }

    /** @return list<array{label: string, url: string, current: bool}> */
    private function versionLinks(?Page $page, ?Version $current = null): array
    {
        $current ??= $page?->version;
        $links = [];
        foreach ($this->config->versions() as $v) {
            if ($v->id === '') {
                return [];
            }
            $target = $page ? $this->repo($v)->find($page->slug) : null;
            $links[] = [
                'label'   => $v->label,
                'url'     => $target?->url($this->config->baseUrl()) ?? $this->config->baseUrl() . rtrim($v->prefix, '/'),
                'current' => $v->id === $current?->id,
            ];
        }

        return $links;
    }

    private function editUrl(Page $page): ?string
    {
        $pattern = (string) $this->config->get('edit_url');
        if ($pattern === '' || !empty($page->meta['no_edit'])) {
            return null;
        }
        $path = ltrim(($page->version->id !== '' ? $page->version->id . '/' : '') . $page->relPath, '/');

        return str_replace('{path}', $path, $pattern);
    }

    private function updatedAt(Page $page): ?int
    {
        if (isset($page->meta['updated'])) {
            $value = $page->meta['updated'];

            return is_int($value) ? $value : (strtotime((string) $value) ?: null);
        }

        // Prefer the last commit date: file mtimes all reset on a fresh clone/deploy.
        static $hasGit = null;
        $hasGit ??= function_exists('shell_exec') && is_dir($this->config->root . '/.git');
        if ($hasGit) {
            $ts = trim((string) @shell_exec('git -C ' . escapeshellarg($this->config->root)
                . ' log -1 --format=%ct -- ' . escapeshellarg($page->source) . ' 2>/dev/null'));
            if (ctype_digit($ts)) {
                return (int) $ts;
            }
        }

        return null;
    }
}
