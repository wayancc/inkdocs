<?php

declare(strict_types=1);

namespace Inkdocs;

/**
 * Site configuration loaded from config.php, merged over sensible defaults.
 */
final class Config
{
    private const DEFAULTS = [
        'site_name'    => 'Inkdocs',
        'description'  => '',
        'site_url'     => '',          // e.g. https://docs.example.com (used for sitemap + canonical)
        'base_url'     => '/',         // path prefix if installed in a subfolder, e.g. /docs/
        'content_dir'  => 'content',
        'theme_dir'    => 'theme',
        'public_dir'   => 'public',
        'cache'        => true,        // write rendered pages to public/cache for web-server delivery
        'debug'        => false,       // show drafts, never cache
        'allow_html'   => true,        // raw HTML inside Markdown (you trust your authors)
        'versions'     => [],          // ['v2' => '2.x', 'v1' => '1.x'] — first entry is "latest"
        'edit_url'     => '',          // https://github.com/you/repo/edit/main/content/{path}
        'nav_links'    => [],          // ['GitHub' => 'https://github.com/you/repo']
        'footer'       => '',
        'highlight'    => true,        // server-side syntax highlighting
        'theme'        => 'wayan',     // wayan (bold, based on wayan.cc) | neutral
        'llms_txt'     => true,        // generate /llms.txt and /llms-full.txt
    ];

    /** @var list<Version>|null */
    private ?array $versions = null;

    private function __construct(
        private array $data,
        public readonly string $root,
    ) {
    }

    public static function load(string $root, array $overrides = []): self
    {
        $file = $root . '/config.php';
        $user = is_file($file) ? require $file : [];
        if (!is_array($user)) {
            throw new \RuntimeException('config.php must return an array.');
        }

        $data = array_replace(self::DEFAULTS, $user, $overrides);
        if (getenv('INKDOCS_DEBUG')) {
            $data['debug'] = true; // set by `bin/inkdocs serve`
        }
        $data['base_url'] = '/' . trim((string) $data['base_url'], '/');
        $data['base_url'] = rtrim($data['base_url'], '/') . '/';

        return new self($data, $root);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function with(array $overrides): self
    {
        $copy = clone $this;
        $copy->data = array_replace($this->data, $overrides);
        $copy->data['base_url'] = rtrim('/' . trim((string) $copy->data['base_url'], '/'), '/') . '/';
        $copy->versions = null;

        return $copy;
    }

    public function path(string $key): string
    {
        $value = (string) $this->get($key);

        return self::isAbsolute($value) ? $value : $this->root . '/' . $value;
    }

    /** "/srv/docs", "C:\docs", "C:/docs" and "\\server\share" are absolute; "content" is not. */
    public static function isAbsolute(string $path): bool
    {
        return preg_match('~^(/|\\\\|[A-Za-z]:[/\\\\])~', $path) === 1;
    }

    public function baseUrl(): string
    {
        return $this->data['base_url'];
    }

    /** @return list<Version> */
    public function versions(): array
    {
        if ($this->versions !== null) {
            return $this->versions;
        }

        $contentDir = $this->path('content_dir');
        $declared = (array) $this->get('versions');

        if ($declared === []) {
            return $this->versions = [new Version('', '', $contentDir, '', true)];
        }

        $list = [];
        $first = true;
        foreach ($declared as $id => $label) {
            $id = (string) $id;
            $list[] = new Version(
                id: $id,
                label: (string) $label,
                dir: $contentDir . '/' . $id,
                prefix: $first ? '' : $id . '/',
                latest: $first,
            );
            $first = false;
        }

        return $this->versions = $list;
    }

    public function latest(): Version
    {
        return $this->versions()[0];
    }

    public function version(string $id): ?Version
    {
        foreach ($this->versions() as $v) {
            if ($v->id === $id) {
                return $v;
            }
        }

        return null;
    }
}
