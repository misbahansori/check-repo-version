<?php

declare(strict_types=1);

/**
 * Dump every Forge site .env file to a local output file.
 *
 * Install:
 *   composer require laravel/forge-sdk:^4.0
 *
 * Usage:
 *   export FORGE_API_TOKEN=your-forge-api-token
 *   ./tempest forge:dump-envs
 *   ./tempest forge:dump-envs --output=custom-output.txt
 */

namespace App\Console\Commands;

use Laravel\Forge\CursorPaginator;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Organization;
use Laravel\Forge\Resources\Server;
use Laravel\Forge\Resources\Site;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Throwable;

final readonly class DumpForgeEnvsCommand
{
    use HasConsole;

    private const int DELAY_MICROSECONDS = 250_000;

    #[ConsoleCommand(name: 'forge:dump-envs')]
    public function dump(string $output = 'forge-envs-output.txt'): ExitCode
    {
        $token = getenv('FORGE_API_TOKEN') ?: ($_ENV['FORGE_API_TOKEN'] ?? null);

        if (! $token) {
            $this->console->error('FORGE_API_TOKEN environment variable is not set.');

            return ExitCode::INVALID;
        }

        $forge = new Forge($token);
        $handle = fopen($output, 'w');

        if ($handle === false) {
            $this->console->error("Could not open output file: {$output}");

            return ExitCode::ERROR;
        }

        $this->console->info("Writing output to {$output}");
        $this->writeLine($handle, 'Forge .env dump — generated at ' . date('c'));
        $this->writeLine($handle, '');

        $orgCount = 0;
        $siteCount = 0;
        $errorCount = 0;

        try {
            foreach ($this->allItems($this->call(fn () => $forge->organizations())) as $organization) {
                $orgCount++;
                $this->console->info("Organization: {$organization->name} ({$organization->slug})");
                $this->writeOrganizationHeader($handle, $organization);

                foreach ($this->allItems($this->call(fn () => $forge->servers($organization->slug))) as $server) {
                    $this->console->info("  Server: {$server->name} (#{$server->id})");
                    $this->writeServerHeader($handle, $server);

                    foreach ($this->allItems($this->call(fn () => $forge->serverSites($organization->slug, $server->id))) as $site) {
                        $siteCount++;
                        $this->console->info("    Site: {$site->name} (#{$site->id})");

                        try {
                            $env = $this->call(fn () => $forge->siteEnvironment($organization->slug, $server->id, $site->id));
                            $this->writeSiteEnv($handle, $site, $env);
                        } catch (Throwable $e) {
                            $errorCount++;
                            $message = $e->getMessage();
                            $this->console->error("    Failed: {$message}");
                            $this->writeSiteError($handle, $site, $message);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            $this->console->error('Fatal error: ' . $e->getMessage());
            fclose($handle);

            return ExitCode::ERROR;
        }

        fclose($handle);

        $this->console->info("Done. {$orgCount} org(s), {$siteCount} site(s), {$errorCount} error(s).");
        $this->console->warn("⚠️  {$output} contains secrets — delete it when done and keep it out of version control.");

        return $errorCount > 0 ? ExitCode::ERROR : ExitCode::SUCCESS;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function call(callable $callback): mixed
    {
        $this->delay();

        return $callback();
    }

    private function delay(): void
    {
        usleep(self::DELAY_MICROSECONDS);
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

    /**
     * @param resource $handle
     */
    private function writeOrganizationHeader($handle, Organization $organization): void
    {
        $this->writeLine($handle, str_repeat('=', 80));
        $this->writeLine($handle, "Organization: {$organization->name} (slug: {$organization->slug}, id: {$organization->id})");
        $this->writeLine($handle, str_repeat('=', 80));
        $this->writeLine($handle, '');
    }

    /**
     * @param resource $handle
     */
    private function writeServerHeader($handle, Server $server): void
    {
        $this->writeLine($handle, str_repeat('-', 80));
        $this->writeLine($handle, "Server: {$server->name} (id: {$server->id})");
        $this->writeLine($handle, str_repeat('-', 80));
        $this->writeLine($handle, '');
    }

    /**
     * @param resource $handle
     */
    private function writeSiteEnv($handle, Site $site, string $env): void
    {
        $this->writeLine($handle, "--- Site: {$site->name} (id: {$site->id}) ---");
        $this->writeLine($handle, $env);
        $this->writeLine($handle, '');
    }

    /**
     * @param resource $handle
     */
    private function writeSiteError($handle, Site $site, string $message): void
    {
        $this->writeLine($handle, "--- Site: {$site->name} (id: {$site->id}) ---");
        $this->writeLine($handle, "ERROR: {$message}");
        $this->writeLine($handle, '');
    }

    /**
     * @param resource $handle
     */
    private function writeLine($handle, string $line): void
    {
        fwrite($handle, $line . PHP_EOL);
    }
}
