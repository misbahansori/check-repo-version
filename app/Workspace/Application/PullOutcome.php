<?php

declare(strict_types=1);

namespace App\Workspace\Application;

final readonly class PullOutcome
{
    public function __construct(
        public string $repository,
        public string $branch,
        public PullStatus $status,
        public string $message,
    ) {
    }
}
