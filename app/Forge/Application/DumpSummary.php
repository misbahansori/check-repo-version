<?php

declare(strict_types=1);

namespace App\Forge\Application;

final readonly class DumpSummary
{
    public function __construct(
        public int $organizationCount,
        public int $siteCount,
        public int $errorCount,
    ) {
    }
}
