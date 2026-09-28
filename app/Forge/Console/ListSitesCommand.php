<?php

declare(strict_types=1);

namespace App\Forge\Console;

use App\Forge\Application\ListSites;
use App\Forge\Application\ServerSites;
use App\Forge\Application\ServerTarget;
use App\Forge\Infrastructure\SitesFileExport;
use App\Shared\Console\ConsoleTable;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Throwable;

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
final readonly class ListSitesCommand
{
    use HasConsole;
    use ConsoleTable;

    private const array HEADERS = ['Domain', 'Repo:branch', 'Project Type', 'Port', 'Site ID', 'Deploy Hook'];

    public function __construct(
        private ListSites $listSites,
    ) {
    }

    /**
     * @param string|null $organization Only include this organization (slug or name, case-insensitive substring).
     * @param string|null $server Only include servers whose name matches (case-insensitive substring).
     * @param bool $all Skip the interactive server picker and list every matching server.
     * @param bool $ports Look up each site's port (one extra API call per site).
     * @param bool $hooks Look up each site's deploy hook when the listing does not include it.
     * @param string|null $output Also write the result to this file (.tsv/.txt = tab separated, otherwise CSV).
     */
    #[ConsoleCommand(name: 'forge:sites')]
    public function __invoke(
        ?string $organization = null,
        ?string $server = null,
        bool $all = false,
        bool $ports = true,
        bool $hooks = true,
        ?string $output = null,
    ): ExitCode {
        if (! $this->listSites->isConfigured()) {
            $this->console->error('FORGE_API_TOKEN environment variable is not set.');

            return ExitCode::INVALID;
        }

        try {
            $targets = $this->pickServers($this->listSites->servers($organization, $server), $all);
        } catch (Throwable $e) {
            $this->console->error('Could not fetch servers: ' . $e->getMessage());

            return ExitCode::ERROR;
        }

        if ($targets === []) {
            $this->console->error('No servers selected.');

            return ExitCode::INVALID;
        }

        /** @var list<ServerSites> $results */
        $results = [];
        $siteCount = 0;
        $errorCount = 0;

        try {
            foreach ($targets as $target) {
                $result = $this->listSites->sitesOn($target, $ports, $hooks);
                $siteCount += $result->siteCount;
                $errorCount += $result->errorCount;

                if ($result->sites !== []) {
                    $results[] = $result;
                }
            }
        } catch (Throwable $e) {
            $this->console->error('Fatal error: ' . $e->getMessage());

            return ExitCode::ERROR;
        }

        if ($results === []) {
            $this->console->error('No sites found for the given filters.');

            return ExitCode::INVALID;
        }

        foreach ($results as $result) {
            $this->console->writeln();
            $this->console->writeln("<strong>{$result->target->server->name}</strong>");
            $this->table(headers: self::HEADERS, rows: array_map(fn ($site) => $site->toArray(), $result->sites));
        }

        if ($output !== null && ! SitesFileExport::write($output, self::HEADERS, $results)) {
            $this->console->error("Could not open output file: {$output}");

            return ExitCode::ERROR;
        }

        $this->console->writeln();
        $this->console->info(sprintf('Done. %d server(s), %d site(s), %d error(s).', count($results), $siteCount, $errorCount));

        if ($output !== null) {
            $this->console->info("Written to {$output}");
            $this->console->warning("⚠️  {$output} contains deploy hook tokens — keep it out of version control.");
        }

        return $errorCount > 0 ? ExitCode::ERROR : ExitCode::SUCCESS;
    }

    /**
     * Let the user pick from the matching servers unless only one matched,
     * `--all` was passed, or prompting is unavailable.
     *
     * @param array<string, ServerTarget> $targets
     * @return array<string, ServerTarget>
     */
    private function pickServers(array $targets, bool $all): array
    {
        if ($targets === [] || $all || count($targets) === 1 || ! $this->console->supportsPrompting()) {
            return $targets;
        }

        $selected = $this->console->ask(
            question: 'Which servers do you want to list?',
            options: array_map(fn (ServerTarget $target) => $target->label(), $targets),
            multiple: true,
            hint: 'Use space to select, enter to confirm.',
        );

        return array_intersect_key($targets, is_array($selected) ? $selected : []);
    }
}
