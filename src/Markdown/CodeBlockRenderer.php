<?php

declare(strict_types=1);

namespace Inkdocs\Markdown;

use Highlight\Highlighter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Renders fenced code with server-side highlighting, so pages ship zero highlighting JS.
 *
 *   ```php title="config.php"
 *   ```mermaid      → left as a diagram for the browser to draw
 */
final class CodeBlockRenderer implements NodeRendererInterface
{
    private ?Highlighter $highlighter;

    public function __construct(bool $highlight = true)
    {
        $this->highlighter = $highlight && class_exists(Highlighter::class) ? new Highlighter() : null;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        FencedCode::assertInstanceOf($node);

        $info = (string) $node->getInfo();
        $lang = strtolower($node->getInfoWords()[0] ?? '');
        $code = $node->getLiteral();

        if ($lang === 'mermaid') {
            return '<pre class="wyn-mermaid">' . self::e($code) . "</pre>\n";
        }

        $title = preg_match('/title="([^"]+)"/', $info, $m) ? $m[1] : null;
        $html = self::e($code);

        if ($this->highlighter !== null && $lang !== '' && !in_array($lang, ['text', 'txt', 'plain'], true)) {
            try {
                $result = $this->highlighter->highlight($lang, $code);
                $html = $result->value;
            } catch (\Throwable) {
                // Unknown language: fall back to escaped plain text.
            }
        }

        $label = $lang !== '' ? ' data-wyn-lang="' . self::e($lang) . '"' : '';
        $head = $title !== null ? '<div class="wyn-code-title">' . self::e($title) . '</div>' : '';

        return '<div class="wyn-code"' . $label . '>' . $head
            . '<pre><code class="hljs' . ($lang !== '' ? ' language-' . self::e($lang) : '') . '">'
            . $html . "</code></pre></div>\n";
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
