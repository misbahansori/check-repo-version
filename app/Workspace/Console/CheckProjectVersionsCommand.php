<?php

declare(strict_types=1);

namespace App\Workspace\Console;

use App\Shared\Console\ConsoleTable;
use App\Workspace\Application\CheckProjectVersions;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class CheckProjectVersionsCommand
{
    use HasConsole;
    use AskForWorkspacePath;
    use ConsoleTable;

    public function __construct(
        private CheckProjectVersions $checkProjectVersions,
    ) {
    }

    #[ConsoleCommand(name: 'repo:check')]
    public function __invoke(bool $cache = true): ExitCode
    {
        $path = $this->askForWorkspacePath($cache);

        if ($path === null) {
            $this->console->error('⚠️  Path is invalid');

            return ExitCode::INVALID;
        }

        $this->console->info("Scanning: {$path}");

        $versions = ($this->checkProjectVersions)($path);

        if ($versions === []) {
            $this->console->error("No project files found in {$path}");

            return ExitCode::INVALID;
        }

        $rows = [];

        foreach ($versions as $version) {
            $rows[] = [$version->project->name, $version->project->type->value, $version->project->version, $version->branch];
        }

        $this->table(headers: ['Project', 'Type', 'Version', 'Branch'], rows: $rows);

        $this->console->info('Repository versions check completed.');

        return ExitCode::SUCCESS;
    }
}
