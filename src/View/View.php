<?php

declare(strict_types=1);

namespace Inkdocs\View;

use Inkdocs\Config;

/**
 * Plain-PHP templates. No template language to learn: themes are .php files in theme/.
 */
final class View
{
    private string $dir;

    public function __construct(private readonly Config $config)
    {
        $this->dir = $config->path('theme_dir');
    }

    public function render(string $template, array $vars): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: {$file}");
        }

        $v = $this;
        $config = $this->config;
        extract($vars, EXTR_SKIP);

        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    public function partial(string $template, array $vars = []): string
    {
        return $this->render($template, $vars);
    }

    public function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function url(string $path = ''): string
    {
        return $this->config->baseUrl() . ltrim($path, '/');
    }

    /** Asset URL with a content hash so browsers can cache it forever. */
    public function asset(string $path): string
    {
        $file = $this->config->path('public_dir') . '/assets/' . ltrim($path, '/');
        $hash = is_file($file) ? substr(md5_file($file) ?: '', 0, 8) : '0';

        return $this->url('assets/' . ltrim($path, '/')) . '?v=' . $hash;
    }
}
