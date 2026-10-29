<?php

declare(strict_types=1);

namespace Inkdocs\Markdown;

final readonly class RenderedPage
{
    /**
     * @param list<array{level: int, id: string, text: string}>      $toc
     * @param list<array{id: string, heading: string, text: string}> $sections
     */
    public function __construct(
        public string $html,
        public array $toc,
        public array $sections,
        public bool $hasH1,
        public bool $hasMermaid,
    ) {
    }
}
