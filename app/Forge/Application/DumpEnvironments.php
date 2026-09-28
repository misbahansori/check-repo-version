<?php

declare(strict_types=1);

namespace App\Forge\Application;

use App\Forge\Domain\ForgeGateway;
use App\Shared\Application\Progress;
use Throwable;

final readonly class DumpEnvironments
{
    public function __construct(
        private ForgeGateway $forge,
        private Progress $progress,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->forge->isConfigured();
    }

    /**
     * Write the .env file of every site in every organization to `$dump`.
     */
    public function __invoke(EnvironmentDump $dump): DumpSummary
    {
        $organizationCount = 0;
        $siteCount = 0;
        $errorCount = 0;

        foreach ($this->forge->organizations() as $organization) {
            $organizationCount++;
            $this->progress->info("Organization: {$organization->name} ({$organization->slug})");
            $dump->organization($organization);

            foreach ($this->forge->servers($organization) as $server) {
                $this->progress->info("  Server: {$server->name} (#{$server->id})");
                $dump->server($server);

                foreach ($this->forge->sites($organization, $server) as $site) {
                    $siteCount++;
                    $this->progress->info("    Site: {$site->name} (#{$site->id})");

                    try {
                        $dump->site($site, $this->forge->environment($organization, $server, $site));
                    } catch (Throwable $e) {
                        $errorCount++;
                        $this->progress->error("    Failed: {$e->getMessage()}");
                        $dump->siteFailed($site, $e->getMessage());
                    }
                }
            }
        }

        return new DumpSummary($organizationCount, $siteCount, $errorCount);
    }
}
