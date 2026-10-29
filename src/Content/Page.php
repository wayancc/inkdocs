<?php

declare(strict_types=1);

namespace Inkdocs\Content;

use Inkdocs\Version;

/**
 * A single Markdown document and the metadata needed to place it in the site.
 */
final class Page
{
    public ?Page $prev = null;
    public ?Page $next = null;

    /** @var list<array{title: string, slug: string}> */
    public array $breadcrumbs = [];

    public function __construct(
        public readonly Version $version,
        public readonly string $source,      // absolute file path
        public readonly string $relPath,     // path relative to the version folder
        public readonly string $slug,        // URL path inside the version, '' for home
        public readonly string $title,
        public readonly string $description,
        public readonly int $order,
        public readonly bool $hidden,
        public readonly array $meta,
        public readonly string $body,        // Markdown without front matter
    ) {
    }

    /** Path relative to the site base, e.g. "v1/guide/install" or "" for the latest home page. */
    public function path(): string
    {
        return rtrim($this->version->prefix . $this->slug, '/');
    }

    /** Where the raw Markdown is served, e.g. "guide/install.md" or "v1/index.md". */
    public function rawPath(): string
    {
        return ($this->slug === '' ? $this->version->prefix . 'index' : $this->path()) . '.md';
    }

    public function url(string $base): string
    {
        $path = $this->path();

        return $path === '' ? $base : $base . $path;
    }

    public function updatedAt(): int
    {
        return (int) filemtime($this->source);
    }
}
