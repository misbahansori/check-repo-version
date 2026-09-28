<?php

declare(strict_types=1);

namespace App\Forge\Application;

use App\Forge\Domain\ForgeGateway;
use App\Forge\Domain\Site;
use App\Forge\Domain\SitePort;
use App\Shared\Application\Progress;
use Throwable;

final readonly class ListSites
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
     * Every server whose organization and name match the (case-insensitive, partial) filters.
     *
     * @return array<string, ServerTarget> Keyed by {@see ServerTarget::key()}.
     */
    public function servers(?string $organization = null, ?string $server = null): array
    {
        $this->progress->writeln('Fetching organizations…');

        $targets = [];

        foreach ($this->forge->organizations() as $org) {
            if ($organization !== null && ! $this->matches($organization, $org->slug) && ! $this->matches($organization, $org->name)) {
                continue;
            }

            $this->progress->writeln("  <strong>{$org->name}</strong> <em>({$org->slug})</em> — fetching servers…");

            foreach ($this->forge->servers($org) as $forgeServer) {
                if ($server !== null && ! $this->matches($server, $forgeServer->name)) {
                    continue;
                }

                $target = new ServerTarget($org, $forgeServer);
                $targets[$target->key()] = $target;
            }
        }

        return $targets;
    }

    public function sitesOn(ServerTarget $target, bool $withPorts = true, bool $withHooks = true): ServerSites
    {
        $org = $target->organization;
        $server = $target->server;

        $this->progress->writeln();
        $this->progress->writeln("<strong>{$server->name}</strong> <em>(#{$server->id}, {$org->name})</em> — fetching sites…");

        $sites = [...$this->forge->sites($org, $server)];
        $total = count($sites);

        $this->progress->writeln("  Found {$total} site(s).");

        $summaries = [];
        $errorCount = 0;

        foreach ($sites as $index => $site) {
            $position = str_pad((string) ($index + 1), strlen((string) $total), ' ', STR_PAD_LEFT);
            $label = "    [{$position}/{$total}] {$site->name}";

            $this->progress->write($label . ' …');

            try {
                $summaries[] = new SiteSummary(
                    domain: $site->name !== '' ? $site->name : '-',
                    repository: $site->repositoryReference(),
                    type: $site->projectType(),
                    port: $withPorts ? $this->port($target, $site) : '',
                    siteId: (string) $site->id,
                    deployHook: $withHooks ? $this->deployHook($target, $site) : '',
                );

                $this->progress->writeln(' <em>done</em>');
            } catch (Throwable $e) {
                $errorCount++;
                $this->progress->writeln();
                $this->progress->error("{$label} failed: {$e->getMessage()}");
            }
        }

        return new ServerSites($target, $summaries, $total, $errorCount);
    }

    /**
     * Read the port from the site's environment file, falling back to its nginx `proxy_pass`.
     */
    private function port(ServerTarget $target, Site $site): string
    {
        try {
            $this->progress->write(' env');
            $port = SitePort::fromEnvironment($this->forge->environment($target->organization, $target->server, $site));

            if ($port !== null) {
                return $port;
            }
        } catch (Throwable) {
            // Site has no environment file — fall through to nginx.
        }

        try {
            $this->progress->write(' nginx');

            return SitePort::fromNginx($this->forge->nginxConfiguration($target->organization, $target->server, $site)) ?? '';
        } catch (Throwable) {
            return '';
        }
    }

    private function deployHook(ServerTarget $target, Site $site): string
    {
        if ($site->hasDeploymentUrl()) {
            return $site->deploymentUrl;
        }

        try {
            $this->progress->write(' hook');

            return $this->forge->deploymentTriggerUrl($target->organization, $target->server, $site);
        } catch (Throwable) {
            return '';
        }
    }

    private function matches(string $needle, string $haystack): bool
    {
        return str_contains(strtolower($haystack), strtolower($needle));
    }
}
