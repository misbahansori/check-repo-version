<?php

declare(strict_types=1);

namespace App\Forge\Application;

final readonly class SiteSummary
{
    public function __construct(
        public string $domain,
        public string $repository,
        public string $type,
        public string $port,
        public string $siteId,
        public string $deployHook,
    ) {
    }

    /**
     * @return list<string>
     */
    public function toArray(): array
    {
        return [$this->domain, $this->repository, $this->type, $this->port, $this->siteId, $this->deployHook];
    }
}
