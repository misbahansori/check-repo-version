<?php

declare(strict_types=1);

namespace App\Forge\Console;

use App\Forge\Application\DumpEnvironments;
use App\Forge\Infrastructure\TextFileEnvironmentDump;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Throwable;

/**
 * Dump every Forge site .env file to a local output file.
 *
 * Usage:
 *   export FORGE_API_TOKEN=your-forge-api-token
 *   ./tempest forge:dump-envs
 *   ./tempest forge:dump-envs --output=custom-output.txt
 */
final readonly class DumpEnvironmentsCommand
{
    use HasConsole;

    public function __construct(
        private DumpEnvironments $dumpEnvironments,
    ) {
    }

    #[ConsoleCommand(name: 'forge:dump-envs')]
    public function __invoke(string $output = 'forge-envs-output.txt'): ExitCode
    {
        if (! $this->dumpEnvironments->isConfigured()) {
            $this->console->error('FORGE_API_TOKEN environment variable is not set.');

            return ExitCode::INVALID;
        }

        $dump = TextFileEnvironmentDump::open($output);

        if ($dump === null) {
            $this->console->error("Could not open output file: {$output}");

            return ExitCode::ERROR;
        }

        $this->console->info("Writing output to {$output}");

        try {
            $summary = ($this->dumpEnvironments)($dump);
        } catch (Throwable $e) {
            $this->console->error('Fatal error: ' . $e->getMessage());

            return ExitCode::ERROR;
        } finally {
            $dump->close();
        }

        $this->console->info("Done. {$summary->organizationCount} org(s), {$summary->siteCount} site(s), {$summary->errorCount} error(s).");
        $this->console->warning("⚠️  {$output} contains secrets — delete it when done and keep it out of version control.");

        return $summary->errorCount > 0 ? ExitCode::ERROR : ExitCode::SUCCESS;
    }
}
