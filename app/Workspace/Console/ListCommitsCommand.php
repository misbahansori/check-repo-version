<?php

declare(strict_types=1);

namespace App\Workspace\Console;

use App\Workspace\Application\ListCommits;
use App\Workspace\Domain\WorkspaceScanner;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class ListCommitsCommand
{
    use HasConsole;
    use AskForWorkspacePath;

    public function __construct(
        private WorkspaceScanner $scanner,
        private ListCommits $listCommits,
    ) {
    }

    #[ConsoleCommand(name: 'repo:commits')]
    public function __invoke(?string $date = null, ?string $author = null, bool $cache = true): ExitCode
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

        $date ??= date('Y-m-d');
        $this->console->info("Checking commits for date: {$date}");

        if ($author !== null) {
            $this->console->info("Filtering by author: {$author}");
        }

        foreach (($this->listCommits)($repositories, $date, $author) as $result) {
            $this->console->writeln("{$result->repository}:");

            foreach ($result->commits as $commit) {
                $this->console->writeln("  - {$commit->message}");
            }

            $this->console->writeln();
        }

        $this->console->info('Git commits check completed.');

        return ExitCode::SUCCESS;
    }
}
