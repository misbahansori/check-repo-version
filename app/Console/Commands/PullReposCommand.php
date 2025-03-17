<?php

namespace App\Console\Commands;

use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;

final readonly class PullReposCommand
{
    use HasConsole;

    #[ConsoleCommand(name: 'repo:pull')]
    public function pull(string $path, ?string $defaultBranch = null): void
    {
        // Normalize path
        $path = str_replace('~', $_SERVER['HOME'], $path);

        if (!is_dir($path)) {
            $this->writeln("<error>Directory not found: {$path}</error>");
            return;
        }

        // Find git repositories
        $this->writeln("<comment>Scanning for Git repositories in {$path}</comment>");
        $repos = $this->findGitRepos($path);

        if (empty($repos)) {
            $this->writeln("<error>No Git repositories found!</error>");
            return;
        }

        $this->writeln("<success>Found " . count($repos) . " repositories</success>");
        $this->writeln("");

        // Process each repository
        $results = [];
        foreach ($repos as $index => $repo) {
            $repoName = basename($repo);
            $this->writeln("<strong>[" . ($index + 1) . "/" . count($repos) . "] {$repoName}</strong>");

            // Remember current directory
            $originalDir = getcwd();
            chdir($repo);

            // Get current branch
            $branchOutput = shell_exec('git branch --show-current');
            $currentBranch = $branchOutput !== null ? trim($branchOutput) : 'unknown';
            $this->writeln("  Current branch: <em>{$currentBranch}</em>");

            // Determine main branch
            $mainBranch = $this->determineMainBranch($defaultBranch);
            if (!$mainBranch) {
                $this->writeln("  <error>No main branch found</error>");
                $results[] = $this->createResult($repoName, $currentBranch, 'error', 'No main branch found');
                chdir($originalDir);
                $this->writeln("");
                continue;
            }

            $this->writeln("  Main branch: <em>{$mainBranch}</em>");

            // Check for uncommitted changes
            if ($this->hasUncommittedChanges()) {
                $this->writeln("  <error>Uncommitted changes detected</error>");
                $results[] = $this->createResult($repoName, $currentBranch, 'skipped', 'Uncommitted changes detected');
                chdir($originalDir);
                $this->writeln("");
                continue;
            }

            // Switch to main branch if needed
            if ($currentBranch !== $mainBranch) {
                $this->writeln("  Switching to {$mainBranch}...");
                $switchOutput = shell_exec("git checkout {$mainBranch} 2>&1");

                if (str_contains($switchOutput, 'error')) {
                    $this->writeln("  <error>Failed to switch branch</error>");
                    $this->writeln("  <comment>{$switchOutput}</comment>");
                    $results[] = $this->createResult($repoName, $currentBranch, 'error', "Failed to switch to {$mainBranch}: {$switchOutput}");
                    chdir($originalDir);
                    $this->writeln("");
                    continue;
                }
            }

            // Pull latest changes
            $this->writeln("  Pulling latest changes...");
            $pullOutput = shell_exec("git pull 2>&1");

            if (
                str_contains(strtolower($pullOutput), 'error') ||
                str_contains(strtolower($pullOutput), 'fatal') ||
                str_contains(strtolower($pullOutput), 'conflict')
            ) {
                $this->writeln("  <error>Pull failed</error>");
                $this->writeln("  <comment>{$pullOutput}</comment>");
                $results[] = $this->createResult($repoName, $mainBranch, 'error', "Pull failed: {$pullOutput}");
            } else {
                $this->writeln("  <success>Pull successful</success>");
                $results[] = $this->createResult($repoName, $mainBranch, 'success', $pullOutput);
            }

            // Return to original directory
            chdir($originalDir);
            $this->writeln("");
        }

        // Display summary table
        $this->displaySummary($results);
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
            'repo' => $repo,
            'branch' => $branch,
            'status' => $status,
            'message' => $message
        ];
    }

    private function displaySummary(array $results): void
    {
        $this->writeln("<h1>Pull Summary</h1>");

        // Count by status
        $successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
        $errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));
        $skippedCount = count(array_filter($results, fn($r) => $r['status'] === 'skipped'));

        $this->writeln("<success>Success: {$successCount}</success> | <error>Errors: {$errorCount}</error> | <comment>Skipped: {$skippedCount}</comment>");
        $this->writeln("");

        // Display table
        $this->displayTable($results, ['Repository', 'Branch', 'Status', 'Message']);
    }

    private function displayTable(array $rows, array $headers): void
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            $values = array_values($row);
            foreach ($values as $i => $value) {
                if ($i === 3) { // Message column
                    $width = min(50, strlen($value)); // Limit message width
                } else {
                    $width = strlen($value);
                }
                $widths[$i] = max($widths[$i] ?? 0, $width);
            }
        }

        // Headers
        $separator = '+';
        $headerRow = '|';

        foreach ($widths as $i => $width) {
            $separator .= str_repeat('-', $width + 2) . '+';
            $headerRow .= ' ' . str_pad($headers[$i], $width) . ' |';
        }

        $this->writeln($separator);
        $this->writeln($headerRow);
        $this->writeln(str_replace('-', '=', $separator));

        // Rows
        foreach ($rows as $row) {
            $values = array_values($row);
            $tableRow = '|';

            foreach ($values as $i => $value) {
                if ($i === 3) { // Message column
                    $value = strlen($value) > $widths[$i]
                        ? substr($value, 0, $widths[$i] - 3) . '...'
                        : $value;
                }

                $cell = ' ' . str_pad($value, $widths[$i]) . ' ';

                // Apply formatting based on column and value
                if ($i === 2) { // Status column
                    if ($value === 'success') {
                        $cell = "<success>{$cell}</success>";
                    } elseif ($value === 'error') {
                        $cell = "<error>{$cell}</error>";
                    } elseif ($value === 'skipped') {
                        $cell = "<comment>{$cell}</comment>";
                    }
                }

                $tableRow .= $cell . '|';
            }

            $this->writeln($tableRow);
        }

        $this->writeln($separator);
    }
}
