<?php

declare(strict_types=1);

namespace App\Forge\Application;

use App\Forge\Domain\Organization;
use App\Forge\Domain\Server;

final readonly class ServerTarget
{
    public function __construct(
        public Organization $organization,
        public Server $server,
    ) {
    }

    public function key(): string
    {
        return "{$this->organization->slug}:{$this->server->id}";
    }

    public function label(): string
    {
        return "{$this->server->name} ({$this->organization->name})";
    }
}
