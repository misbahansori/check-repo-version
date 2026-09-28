<?php

declare(strict_types=1);

namespace App\Forge\Domain;

interface ForgeGateway
{
    public function isConfigured(): bool;

    /**
     * @return iterable<Organization>
     */
    public function organizations(): iterable;

    /**
     * @return iterable<Server>
     */
    public function servers(Organization $organization): iterable;

    /**
     * @return iterable<Site>
     */
    public function sites(Organization $organization, Server $server): iterable;

    public function environment(Organization $organization, Server $server, Site $site): string;

    public function nginxConfiguration(Organization $organization, Server $server, Site $site): string;

    public function deploymentTriggerUrl(Organization $organization, Server $server, Site $site): string;
}
