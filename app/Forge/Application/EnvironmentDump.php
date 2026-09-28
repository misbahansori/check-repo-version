<?php

declare(strict_types=1);

namespace App\Forge\Application;

use App\Forge\Domain\Organization;
use App\Forge\Domain\Server;
use App\Forge\Domain\Site;

interface EnvironmentDump
{
    public function organization(Organization $organization): void;

    public function server(Server $server): void;

    public function site(Site $site, string $environment): void;

    public function siteFailed(Site $site, string $message): void;
}
