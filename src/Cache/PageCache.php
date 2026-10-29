<?php

declare(strict_types=1);

namespace Inkdocs\Cache;

/**
 * Writes rendered output to public/cache so the web server can serve it without PHP.
 *
 *   ""               → cache/index.html
 *   "guide/install"  → cache/guide/install/index.html
 *   "v1/search.json" → cache/v1/search.json
 */
final class PageCache
{
    public function __construct(private readonly string $dir)
    {
    }

    public static function relativeFile(string $path): string
    {
        $path = trim($path, '/');
        if (preg_match('/\.(json|xml|txt|md)$/', $path)) {
            return $path;
        }

        return ltrim($path . '/index.html', '/');
    }

    public function file(string $path): string
    {
        return $this->dir . '/' . self::relativeFile($path);
    }

    public function write(string $path, string $contents): void
    {
        self::put($this->file($path), $contents);
    }

    public function clear(): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $count = 0;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            if (in_array($file->getFilename(), ['.gitignore', '.gitkeep'], true)) {
                continue;
            }
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
                $count++;
            }
        }

        return $count;
    }

    /** Atomic write: readers never see a half-written page. */
    public static function put(string $file, string $contents): void
    {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create directory: {$dir}");
        }

        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmp, $contents, LOCK_EX);
        rename($tmp, $file);
    }
}
