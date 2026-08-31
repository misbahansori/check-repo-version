<?php

declare(strict_types=1);

/**
 * List every Forge site grouped per server, with its repo, branch, project type,
 * port, site id and deploy hook.
 *
 * Usage:
 *   export FORGE_API_TOKEN=your-forge-api-token
 *   ./tempest forge:sites                       # pick the servers interactively
 *   ./tempest forge:sites --all                 # every server, no prompt
 *   ./tempest forge:sites --server=bliink-production
 *   ./tempest forge:sites --organization=gilgameshsg --output=forge-sites.tsv
 *   ./tempest forge:sites --no-ports --no-hooks # skip the slow per-site lookups
 */

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ConsoleTable;
use Laravel\Forge\CursorPaginator;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Organization;
use Laravel\Forge\Resources\Server;
use Laravel\Forge\Resources\Site;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Throwable;

final readonly class ListForgeSitesCommand
{
    use HasConsole;
    use ConsoleTable;

    private const int DELAY_MICROSECONDS = 250_000;

    private const array HEADERS = ['Domain', 'Repo:branch', 'Project Type', 'Port', 'Site ID', 'Deploy Hook'];

    /** Environment keys that commonly hold the port a site listens on. */
    private const array PORT_KEYS = ['PORT', 'APP_PORT', 'NUXT_PORT', 'NITRO_PORT', 'SERVER_PORT', 'HTTP_PORT'];

    /**
     * @param string|null $organization Only include this organization (slug or name, case-insensitive substring).
     * @param string|null $server Only include servers whose name matches (case-insensitive substring).
     * @param bool $all Skip the interactive server picker and list every matching server.
     * @param bool $ports Look up each site's port (one extra API call per site).
     * @param bool $hooks Look up each site's deploy hook when the listing does not include it.
     * @param string|null $output Also write the result to this file (.tsv/.txt = tab separated, otherwise CSV).
     */
    #[ConsoleCommand(name: 'forge:sites')]
    public function list(
        ?string $organization = null,
        ?string $server = null,
        bool $all = false,
        bool $ports = true,
        bool $hooks = true,
        ?string $output = null,
    ): ExitCode {
        $token = getenv('FORGE_API_TOKEN') ?: ($_ENV['FORGE_API_TOKEN'] ?? null);

        if (! $token) {
            $this->console->error('FORGE_API_TOKEN environment variable is not set.');

            return ExitCode::INVALID;
        }

        $forge = new Forge($token);

        try {
            $targets = $this->resolveTargets($forge, $organization, $server, $all);
        } catch (Throwable $e) {
            $this->console->error('Could not fetch servers: ' . $e->getMessage());

            return ExitCode::ERROR;
        }

        if ($targets === []) {
            $this->console->error('No servers selected.');

            return ExitCode::INVALID;
        }

        /** @var array<string, array<int, array<string, string>>> $grouped */
        $grouped = [];
        $siteCount = 0;
        $errorCount = 0;

        try {
            foreach ($targets as ['organization' => $org, 'server' => $forgeServer]) {
                $this->console->writeln('');
                $this->console->writeln("<strong>{$forgeServer->name}</strong> <em>(#{$forgeServer->id}, {$org->name})</em> — fetching sites…");

                $sites = iterator_to_array($this->allItems($this->call(fn () => $forge->serverSites($org->slug, $forgeServer->id))), preserve_keys: false);
                $total = count($sites);

                $this->console->writeln("  Found {$total} site(s).");

                $rows = [];

                foreach ($sites as $index => $site) {
                    $siteCount++;
                    $position = str_pad((string) ($index + 1), strlen((string) $total), ' ', STR_PAD_LEFT);
                    $label = "    [{$position}/{$total}] {$site->name}";

                    $this->console->write($label . ' …');

                    try {
                        $rows[] = [
                            'domain' => $site->name ?? '-',
                            'repo' => $this->repository($site),
                            'type' => $this->projectType($site),
                            'port' => $ports ? $this->port($forge, $org->slug, $forgeServer, $site) : '',
                            'site_id' => (string) ($site->id ?? ''),
                            'deploy_hook' => $hooks ? $this->deployHook($forge, $org->slug, $forgeServer, $site) : '',
                        ];

                        $this->console->writeln(' <em>done</em>');
                    } catch (Throwable $e) {
                        $errorCount++;
                        $this->console->writeln('');
                        $this->console->error("{$label} failed: {$e->getMessage()}");
                    }
                }

                if ($rows !== []) {
                    $grouped[$forgeServer->name] = $rows;
                }
            }
        } catch (Throwable $e) {
            $this->console->error('Fatal error: ' . $e->getMessage());

            return ExitCode::ERROR;
        }

        if ($grouped === []) {
            $this->console->error('No sites found for the given filters.');

            return ExitCode::INVALID;
        }

        foreach ($grouped as $serverName => $rows) {
            $this->console->writeln('');
            $this->console->writeln("<strong>{$serverName}</strong>");
            $this->table(headers: self::HEADERS, rows: $rows);
        }

        if ($output !== null && ! $this->export($output, $grouped)) {
            return ExitCode::ERROR;
        }

        $this->console->writeln('');
        $this->console->info(sprintf('Done. %d server(s), %d site(s), %d error(s).', count($grouped), $siteCount, $errorCount));

        if ($output !== null) {
            $this->console->info("Written to {$output}");
            $this->console->warning("⚠️  {$output} contains deploy hook tokens — keep it out of version control.");
        }

        return $errorCount > 0 ? ExitCode::ERROR : ExitCode::SUCCESS;
    }

    private function matches(string $needle, ?string $haystack): bool
    {
        return $haystack !== null && str_contains(strtolower($haystack), strtolower($needle));
    }

    /**
     * Collect every server matching the filters, then let the user pick from them
     * unless a single server matched, `--all` was passed, or prompting is unavailable.
     *
     * @return array<string, array{organization: Organization, server: Server}>
     */
    private function resolveTargets(Forge $forge, ?string $organization, ?string $server, bool $all): array
    {
        $this->console->writeln('Fetching organizations…');

        $targets = [];

        foreach ($this->allItems($this->call(fn () => $forge->organizations())) as $org) {
            if ($organization !== null && ! $this->matches($organization, $org->slug) && ! $this->matches($organization, $org->name)) {
                continue;
            }

            $this->console->writeln("  <strong>{$org->name}</strong> <em>({$org->slug})</em> — fetching servers…");

            foreach ($this->allItems($this->call(fn () => $forge->servers($org->slug))) as $forgeServer) {
                if ($server !== null && ! $this->matches($server, $forgeServer->name)) {
                    continue;
                }

                $targets["{$org->slug}:{$forgeServer->id}"] = [
                    'organization' => $org,
                    'server' => $forgeServer,
                ];
            }
        }

        if ($targets === [] || $all || count($targets) === 1 || ! $this->console->supportsPrompting()) {
            return $targets;
        }

        $options = [];

        foreach ($targets as $key => ['organization' => $org, 'server' => $forgeServer]) {
            $options[$key] = "{$forgeServer->name} ({$org->name})";
        }

        $selected = $this->console->ask(
            question: 'Which servers do you want to list?',
            options: $options,
            multiple: true,
            hint: 'Use space to select, enter to confirm.',
        );

        return array_intersect_key($targets, is_array($selected) ? $selected : []);
    }

    /**
     * Format the site repository as `owner/repo:branch`.
     */
    private function repository(Site $site): string
    {
        $repository = $site->repository;
        $branch = $site->attributes['repository_branch'] ?? null;

        if (is_array($repository)) {
            $branch = $repository['branch'] ?? $branch;
            $repository = $repository['name'] ?? $repository['url'] ?? null;
        }

        if (! is_string($repository) || $repository === '') {
            return '-';
        }

        // Forge returns the full clone URL — reduce it to `owner/repo`.
        if (preg_match('/[:\/]([^\/]+\/[^\/]+?)(?:\.git)?$/', $repository, $matches) === 1) {
            $repository = $matches[1];
        }

        return is_string($branch) && $branch !== ''
            ? "{$repository}:{$branch}"
            : $repository;
    }

    /**
     * Forge reports most non-PHP sites as "Custom", so fall back to sniffing
     * the deployment script for the toolchain the site actually uses.
     */
    private function projectType(Site $site): string
    {
        $appType = $site->appType;

        if (is_string($appType) && $appType !== '' && strtolower($appType) !== 'custom') {
            return $appType;
        }

        $script = strtolower((string) ($site->deploymentScript ?? ''));

        return match (true) {
            str_contains($script, 'artisan') => 'Laravel',
            str_contains($script, 'nuxt') || str_contains($script, '.output/server') => 'Nuxt',
            str_contains($script, 'next build') => 'Next',
            str_contains($script, 'go build') || str_contains($script, 'go mod') => 'GO',
            str_contains($script, 'npm') || str_contains($script, 'pnpm') || str_contains($script, 'yarn') || str_contains($script, 'node ') => 'Node',
            default => is_string($appType) && $appType !== '' ? $appType : '-',
        };
    }

    /**
     * Resolve the port the site listens on, from its environment file first and
     * from the nginx `proxy_pass` directive as a fallback.
     */
    private function port(Forge $forge, string $organizationSlug, Server $server, Site $site): string
    {
        try {
            $this->console->write(' env');
            $env = $this->call(fn () => $forge->siteEnvironment($organizationSlug, $server->id, $site->id));

            foreach (self::PORT_KEYS as $key) {
                if (preg_match('/^\s*' . $key . '\s*=\s*"?(\d{2,5})"?\s*$/m', $env, $matches) === 1) {
                    return $matches[1];
                }
            }
        } catch (Throwable) {
            // Site has no environment file — fall through to nginx.
        }

        try {
            $this->console->write(' nginx');
            $nginx = $this->call(fn () => $forge->siteNginx($organizationSlug, $server->id, $site->id));

            if (preg_match('/proxy_pass\s+https?:\/\/[^:\s]+:(\d{2,5})/', $nginx, $matches) === 1) {
                return $matches[1];
            }
        } catch (Throwable) {
            // No nginx configuration available.
        }

        return '';
    }

    private function deployHook(Forge $forge, string $organizationSlug, Server $server, Site $site): string
    {
        if (is_string($site->deploymentUrl) && $site->deploymentUrl !== '') {
            return $site->deploymentUrl;
        }

        try {
            $this->console->write(' hook');

            return $this->call(fn () => $forge->deploymentTriggerUrl($organizationSlug, $server->id, $site->id));
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param array<string, array<int, array<string, string>>> $grouped
     */
    private function export(string $output, array $grouped): bool
    {
        $handle = fopen($output, 'w');

        if ($handle === false) {
            $this->console->error("Could not open output file: {$output}");

            return false;
        }

        $useTabs = in_array(strtolower(pathinfo($output, PATHINFO_EXTENSION)), ['tsv', 'txt'], strict: true);

        $write = function (array $fields) use ($handle, $useTabs): void {
            if ($useTabs) {
                fwrite($handle, implode("\t", $fields) . PHP_EOL);

                return;
            }

            fputcsv($handle, $fields, ',', '"', '\\');
        };

        foreach ($grouped as $serverName => $rows) {
            $write([$serverName]);
            $write(self::HEADERS);

            foreach ($rows as $row) {
                $write(array_values($row));
            }

            $write([]);
        }

        fclose($handle);

        return true;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function call(callable $callback): mixed
    {
        usleep(self::DELAY_MICROSECONDS);

        return $callback();
    }

    /**
     * @return iterable<mixed>
     */
    private function allItems(CursorPaginator $paginator): iterable
    {
        $page = $paginator;

        while ($page !== null) {
            foreach ($page->items() as $item) {
                yield $item;
            }

            if (! $page->hasMorePages()) {
                break;
            }

            $page = $this->call(fn () => $page->nextPage());
        }
    }
}
