<?php

namespace App\Console\Commands;

use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use App\Console\Commands\Concerns\AskForPath;
use App\Console\Commands\Concerns\ConsoleTable;

final readonly class CheckGitCommitsCommand
{
    use HasConsole;
    use AskForPath;
    use ConsoleTable;

    #[ConsoleCommand(name: 'repo:commits')]
    public function check(?string $date = null, ?string $author = null, bool $cache = true)
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

        // Use provided date or default to today
        $targetDate = $date ?: date('Y-m-d');
        $this->console->info("Checking commits for date: {$targetDate}");

        if ($author) {
            $this->console->info("Filtering by author: {$author}");
        }

        $results = $this->processRepos($repos, $targetDate, $author);

        foreach ($results as $result) {
            if (empty($result['commits'])) continue;

            $this->console->writeln("{$result['name']}:");
            foreach ($result['commits'] as $commit) {
                $this->console->writeln("  - {$commit['message']}");
            }
            $this->console->writeln('');
        }

        $this->console->info('Git commits check completed.');
    }

    protected function processRepos(array $repos, string $targetDate, ?string $author = null): array
    {
        $results = [];

        foreach ($repos as $index => $repo) {
            $repoName = basename($repo);

            $originalDir = getcwd();
            chdir($repo);

            $commits = $this->getCommitsForDate($targetDate, $author);

            $results[] = [
                'name'    => $repoName,
                'commits' => $commits,
            ];

            // Return to original directory
            chdir($originalDir);
        }

        return $results;
    }

    private function findGitRepos(string $path): array
    {
        $gitDirs = glob("{$path}/**/.git", GLOB_ONLYDIR);
        return array_map('dirname', $gitDirs);
    }

    private function getCommitsForDate(string $date, ?string $author = null): array
    {
        // Build git log command with optional author filter
        $command = "git log --since='{$date} 00:00:00' --until='{$date} 23:59:59'";

        if ($author) {
            $command .= " --author='{$author}'";
        }

        $command .= " --pretty=format:'%H|%an|%ad|%s' --date=short";

        $output = shell_exec($command);

        if (!$output || trim($output) === '') {
            return [];
        }

        $commits = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) >= 4) {
                $commits[] = [
                    'hash' => $parts[0],
                    'author' => $parts[1],
                    'date' => $parts[2],
                    'message' => $parts[3],
                ];
            }
        }

        return $commits;
    }
}
