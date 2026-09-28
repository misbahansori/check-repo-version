<?php

declare(strict_types=1);

namespace App\Workspace\Console;

use App\Shared\Console\ConsoleTable;
use App\Workspace\Application\PullRepositories;
use App\Workspace\Domain\WorkspaceScanner;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Support\Str\ImmutableString;

final readonly class PullRepositoriesCommand
{
    use HasConsole;
    use AskForWorkspacePath;
    use ConsoleTable;

    public function __construct(
        private WorkspaceScanner $scanner,
        private PullRepositories $pullRepositories,
    ) {
    }

    #[ConsoleCommand(name: 'repo:pull')]
    public function __invoke(bool $cache = true, ?string $defaultBranch = null): ExitCode
    {
        $path = $this->askForWorkspacePath($cache);

        if ($path === null) {
            $this->console->error('⚠️  Path is invalid');

            return ExitCode::INVALID;
        }

        $this->console->info("Scanning for Git repositories in {$path}");

        $repositories = $this->scanner->repositories($path);

        if ($repositories === []) {
            $this->console->error('No Git repositories found!');

            return ExitCode::INVALID;
        }

        $this->console->info('Found ' . count($repositories) . ' repositories');

        $rows = [];

        foreach (($this->pullRepositories)($repositories, $defaultBranch) as $outcome) {
            $rows[] = [$outcome->repository, $outcome->branch, $outcome->status->value, $this->summarize($outcome->message)];
        }

        $this->table(headers: ['Repository', 'Branch', 'Status', 'Message'], rows: $rows);

        $this->console->info('Pull repositories completed.');

        return ExitCode::SUCCESS;
    }

    private function summarize(string $message): string
    {
        return new ImmutableString($message)
            ->replace("\n", ' ')
            ->replace("\r", ' ')
            ->trim()
            ->truncate(50, '...')
            ->toString();
    }
}
