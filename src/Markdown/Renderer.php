<?php

declare(strict_types=1);

namespace Inkdocs\Markdown;

use Inkdocs\Content\Page;
use Inkdocs\Content\Repository;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;

/**
 * Markdown → HTML with the extras a docs site needs:
 *  - GitHub Flavored Markdown, footnotes, heading anchors
 *  - links to other .md files rewritten to page URLs (so docs also work when browsed on GitHub)
 *  - GitHub-style callouts: > [!NOTE], > [!TIP], > [!IMPORTANT], > [!WARNING], > [!CAUTION]
 *  - server-side syntax highlighting, ```lang title="file.php" code titles, ```mermaid diagrams
 *  - a table of contents and search sections extracted from the same parse
 */
final class Renderer
{
    private const CALLOUTS = 'NOTE|TIP|IMPORTANT|WARNING|CAUTION';

    private MarkdownParser $parser;
    private HtmlRenderer $html;

    /** @var list<array{source: string, link: string}> */
    private array $broken = [];

    public function __construct(bool $allowHtml = true, bool $highlight = true)
    {
        $env = new Environment([
            'html_input'         => $allowHtml ? 'allow' : 'escape',
            'allow_unsafe_links' => false,
            'heading_permalink'  => [
                'html_class'          => 'wyn-anchor',
                'id_prefix'           => '',
                'fragment_prefix'     => '',
                'apply_id_to_heading' => true,
                'insert'              => 'after',
                'min_heading_level'   => 2,
                'max_heading_level'   => 4,
                'title'               => 'Link to this section',
                'symbol'              => '#',
                'aria_hidden'         => true,
            ],
        ]);

        $env->addExtension(new CommonMarkCoreExtension());
        $env->addExtension(new GithubFlavoredMarkdownExtension());
        $env->addExtension(new FootnoteExtension());
        $env->addExtension(new HeadingPermalinkExtension());
        $env->addRenderer(FencedCode::class, new CodeBlockRenderer($highlight), 10);

        $this->parser = new MarkdownParser($env);
        $this->html = new HtmlRenderer($env);
    }

    public function render(Page $page, Repository $repo, string $base): RenderedPage
    {
        $doc = $this->parser->parse($page->body);

        $toc = [];
        $hasMermaid = false;
        $hasH1 = false;

        foreach ($this->collect($doc) as $node) {
            match (true) {
                $node instanceof Link, $node instanceof Image => $this->rewriteUrl($node, $page, $repo, $base),
                $node instanceof BlockQuote => $this->callout($node),
                $node instanceof FencedCode => $hasMermaid = $hasMermaid || ($node->getInfoWords()[0] ?? '') === 'mermaid',
                $node instanceof Heading => $this->heading($node, $toc, $hasH1),
                default => null,
            };
        }

        $html = self::headlineStop((string) $this->html->renderDocument($doc));

        return new RenderedPage(
            html: $html,
            toc: $toc,
            sections: $this->sections($html),
            hasH1: $hasH1,
            hasMermaid: $hasMermaid,
        );
    }

    /** Links to .md files that could not be resolved, collected across every render. */
    public function brokenLinks(): array
    {
        return $this->broken;
    }

    /** @return list<Node> */
    private function collect(Document $doc): array
    {
        $nodes = [];
        $walker = $doc->walker();
        while ($event = $walker->next()) {
            if ($event->isEntering()) {
                $nodes[] = $event->getNode();
            }
        }

        return $nodes;
    }

    private function heading(Heading $node, array &$toc, bool &$hasH1): void
    {
        if ($node->getLevel() === 1) {
            $hasH1 = true;

            return;
        }
        if ($node->getLevel() > 3) {
            return;
        }

        foreach ($node->children() as $child) {
            if ($child instanceof HeadingPermalink) {
                $toc[] = ['level' => $node->getLevel(), 'id' => $child->getSlug(), 'text' => self::text($node)];
            }
        }
    }

    private function rewriteUrl(Link|Image $node, Page $page, Repository $repo, string $base): void
    {
        $url = $node->getUrl();

        // Root-relative links are relative to the docs site, so honour a subfolder install.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            $node->setUrl(rtrim($base, '/') . $url);

            return;
        }

        if ($url === '' || $url[0] === '#' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            return;
        }

        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        if (!preg_match('/\.md$/i', (string) $path)) {
            return;
        }

        $target = self::resolvePath(dirname($page->relPath), rawurldecode((string) $path));
        $found = $repo->findBySource($target);

        if ($found === null) {
            $this->broken[] = ['source' => $page->version->prefix . $page->relPath, 'link' => $url];
            $node->data->append('attributes/class', 'wyn-broken-link');

            return;
        }

        $node->setUrl($found->url($base) . ($fragment !== null ? '#' . $fragment : ''));
    }

    private function callout(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();
        if (!$paragraph instanceof Paragraph) {
            return;
        }

        $buffer = '';
        $consumed = [];
        for ($n = $paragraph->firstChild(); $n instanceof Text; $n = $n->next()) {
            $buffer .= $n->getLiteral();
            $consumed[] = $n;
            if (str_contains($buffer, ']') || strlen($buffer) > 16) {
                break;
            }
        }

        if (!preg_match('/^\[!(' . self::CALLOUTS . ')\]/i', $buffer, $m)) {
            return;
        }

        foreach ($consumed as $n) {
            $n->detach();
        }

        $rest = ltrim(substr($buffer, strlen($m[0])));
        if ($rest !== '') {
            $paragraph->prependChild(new Text($rest));
        } elseif ($paragraph->firstChild() instanceof Newline) {
            $paragraph->firstChild()->detach();
        }
        if (!$paragraph->hasChildren()) {
            $paragraph->detach();
        }

        $type = strtolower($m[1]);
        $quote->data->set('attributes/class', 'wyn-callout wyn-callout-' . $type);
        $quote->data->set('attributes/data-wyn-callout', ucfirst($type));
    }

    /**
     * Split the rendered page at each <h2> so search can jump straight to a section.
     *
     * @return list<array{id: string, heading: string, text: string}>
     */
    private function sections(string $html): array
    {
        $html = preg_replace('~<a[^>]*class="wyn-anchor"[^>]*>.*?</a>~s', '', $html) ?? $html;
        $html = preg_replace('~<(script|style|h1)\b.*?</\1>~s', '', $html) ?? $html;
        $chunks = preg_split('~(?=<h2[\s>])~', $html) ?: [];

        $sections = [];
        foreach ($chunks as $chunk) {
            $id = '';
            $heading = '';
            if (preg_match('~^<h2([^>]*)>(.*?)</h2>~s', $chunk, $m)) {
                $id = preg_match('~\bid="([^"]*)"~', $m[1], $idm) ? $idm[1] : '';
                $heading = self::plain($m[2]);
                $chunk = substr($chunk, strlen($m[0]));
            }
            $text = self::plain($chunk);
            if ($heading === '' && $text === '') {
                continue;
            }
            $sections[] = ['id' => $id, 'heading' => $heading, 'text' => mb_substr($text, 0, 2000)];
        }

        return $sections;
    }

    /** Bold theme: a page title ends with a full stop ("INSTALLATION."), unless it already ends in punctuation. */
    public static function headlineStop(string $html): string
    {
        return preg_replace_callback('~<h1([^>]*)>(.*?)</h1>~s', static function (array $m): string {
            $text = rtrim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return preg_match('/[.!?:…]$/u', $text)
                ? $m[0]
                : '<h1' . $m[1] . '>' . $m[2] . '<span class="wyn-dot">.</span></h1>';
        }, $html, 1) ?? $html;
    }

    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function text(Node $node): string
    {
        $out = '';
        foreach ($node->children() as $child) {
            if ($child instanceof HeadingPermalink) {
                continue;
            }
            $out .= match (true) {
                $child instanceof Text, $child instanceof Code => $child->getLiteral(),
                default => self::text($child),
            };
        }

        return trim($out);
    }

    private static function resolvePath(string $dir, string $path): string
    {
        $parts = [];
        foreach (explode('/', ($dir === '.' ? '' : $dir) . '/' . $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $segment;
        }

        return implode('/', $parts);
    }
}
