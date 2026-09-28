<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

final readonly class Commit
{
    public function __construct(
        public string $hash,
        public string $author,
        public string $date,
        public string $message,
    ) {
    }
}
