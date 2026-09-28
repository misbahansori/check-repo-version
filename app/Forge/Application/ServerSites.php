<?php

declare(strict_types=1);

namespace App\Forge\Application;

final readonly class ServerSites
{
    /**
     * @param list<SiteSummary> $sites Sites that were summarized successfully.
     */
    public function __construct(
        public ServerTarget $target,
        public array $sites,
        public int $siteCount,
        public int $errorCount,
    ) {
    }
}
