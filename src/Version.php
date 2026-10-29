<?php

declare(strict_types=1);

namespace Inkdocs;

/**
 * One documentation version. The latest version is served at the site root,
 * older versions under their id, e.g. /v1/getting-started.
 */
final readonly class Version
{
    public function __construct(
        public string $id,
        public string $label,
        public string $dir,
        public string $prefix,   // '' for latest, 'v1/' for others
        public bool $latest,
    ) {
    }
}
