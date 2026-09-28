<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

final readonly class GitResult
{
    public function __construct(
        public bool $successful,
        public string $output,
    ) {
    }
}
