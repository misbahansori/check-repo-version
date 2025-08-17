<?php

namespace App\Console\Commands;


use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use Tempest\Support\Str\ImmutableString;
use App\Console\Commands\Concerns\AskForPath;
use App\Console\Commands\Concerns\ConsoleTable;

final readonly class PullReposCommand
{
    use HasConsole;
    use AskForPath;
    use ConsoleTable;

    #[ConsoleCommand(name: 'repo:pull')]
    public function pull(bool $cache = true, ?string $defaultBranch = null)
    {
        $path = $this->askForPath(shouldUseCache: $cache);

        if (!$path) {
            $this->console->error("⚠️  Path: {$path} is invalid ");
            return ExitCode::INVALID;
        }

        $this->console->info("Scanning for Git repositories in {$path}");

        $repos = $this->findGitRepos($path);

        if (empty($repos)) {
            $this->console->error("No Git repositories found!");
            return ExitCode::INVALID;
        }

        $this->console->info("Found " . count($repos) . " repositories");

        $results = $this->processRepos($repos, $defaultBranch);

        $this->table(
            headers: ['Repository', 'Branch', 'Status', 'Message'],
            rows: $results,
        );

        $this->console->info('Pull repositories completed.');
    }

    protected function processRepos(array $repos, ?string $defaultBranch = null): array
    {
        $results = [];

        foreach ($repos as $index => $repo) {
            $repoName = basename($repo);
            $this->console->info("[" . ($index + 1) . "/" . count($repos) . "] {$repoName}");

            $originalDir = getcwd();
            chdir($repo);

            $currentBranch = $this->getGitBranch($repo);
            $this->console->info("  Current branch: <em>{$currentBranch}</em>");

            $mainBranch = $this->determineMainBranch($defaultBranch);
            if (!$mainBranch) {
                $this->console->error("No main branch found");
                $results[] = $this->createResult($repoName, $currentBranch, 'error', 'No main branch found');
                chdir($originalDir);
                continue;
            }

            $this->console->info("  Main branch: <em>{$mainBranch}</em>");

            // Check for uncommitted changes
            if ($this->hasUncommittedChanges()) {
                $this->console->error("Uncommitted changes detected");
                $results[] = $this->createResult($repoName, $currentBranch, 'skipped', 'Uncommitted changes detected');
                chdir($originalDir);
                continue;
            }

            // Switch to main branch if needed
            if ($currentBranch !== $mainBranch) {
                $this->console->info("  Switching to {$mainBranch}...");
                $switchOutput = shell_exec("git checkout {$mainBranch} 2>&1");

                if (str_contains($switchOutput, 'error')) {
                    $this->console->error("Failed to switch branch");
                    $this->console->error("  {$switchOutput}");
                    $results[] = $this->createResult($repoName, $currentBranch, 'error', "Failed to switch to {$mainBranch}: {$switchOutput}");
                    chdir($originalDir);
                    continue;
                }
            } else {
                $this->console->info("  Already on main branch {$mainBranch}");
            }

            // Pull latest changes
            $this->console->info("  Pulling latest changes...");
            $pullOutput = shell_exec("git pull 2>&1");

            if (
                str_contains(strtolower($pullOutput), 'error') ||
                str_contains(strtolower($pullOutput), 'fatal') ||
                str_contains(strtolower($pullOutput), 'conflict')
            ) {
                $this->console->error("Pull failed");
                $this->console->error("  {$pullOutput}");
                $results[] = $this->createResult($repoName, $mainBranch, 'error', "Pull failed: {$pullOutput}");
            } else {
                $this->console->info("Pull successful");
                $results[] = $this->createResult($repoName, $mainBranch, 'success', $pullOutput);
            }

            // Return to original directory
            chdir($originalDir);
        }

        return $results;
    }

    private function getGitBranch(string $repo): string
    {
        $branchOutput = shell_exec("git branch --show-current");

        return $branchOutput !== null ? trim($branchOutput) : 'unknown';
    }

    private function findGitRepos(string $path): array
    {
        $gitDirs = glob("{$path}/**/.git", GLOB_ONLYDIR);
        return array_map('dirname', $gitDirs);
    }

    private function determineMainBranch(?string $default = null): ?string
    {
        // Use provided default if specified
        if ($default) {
            $branchOutput = shell_exec("git rev-parse --verify --quiet {$default} 2>/dev/null");
            $branchExists = $branchOutput !== null && trim($branchOutput) !== '';
            if ($branchExists) {
                return $default;
            }
        }

        // Check common main branch names
        $commonBranches = ['main', 'master', 'develop', 'trunk'];
        foreach ($commonBranches as $branch) {
            $result = shell_exec("git branch --list {$branch}");
            if ($result !== null && str_contains($result, $branch)) {
                return $branch;
            }
        }

        // No main branch found
        return null;
    }

    private function hasUncommittedChanges(): bool
    {
        $status = shell_exec('git status --porcelain');
        return $status !== null && trim($status) !== '';
    }

    private function createResult(string $repo, string $branch, string $status, string $message): array
    {
        return [
            'repository' => $repo,
            'branch'     => $branch,
            'status'     => $status,
            'message'    => new ImmutableString($message)
                ->replace("\n", ' ')
                ->replace("\r", ' ')
                ->trim()
                ->truncate(50, '...')
                ->toString(),
        ];
    }
}
