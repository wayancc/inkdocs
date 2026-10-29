<?php

declare(strict_types=1);

namespace Inkdocs\Http;

final readonly class Response
{
    public function __construct(
        public int $status,
        public string $body,
        public string $contentType,
        public array $headers = [],
        public ?string $cacheKey = null,   // set when the response may be written to the page cache
    ) {
    }

    public function send(bool $head = false): void
    {
        http_response_code($this->status);
        header('Content-Type: ' . $this->contentType);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!$head) {
            echo $this->body;
        }
    }
}
