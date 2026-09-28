<?php

declare(strict_types=1);

namespace App\Workspace\Infrastructure;

use App\Workspace\Domain\Commit;
use App\Workspace\Domain\Git;
use App\Workspace\Domain\GitResult;
use Tempest\Container\Autowire;

#[Autowire]
final readonly class GitCli implements Git
{
    private const array MAIN_BRANCH_CANDIDATES = ['main', 'master', 'develop', 'trunk'];

    public function isRepository(string $path): bool
    {
        return is_dir($path . '/.git');
    }

    public function currentBranch(string $path): ?string
    {
        $result = $this->git($path, ['branch', '--show-current']);
        $branch = trim($result->output);

        return $result->successful && $branch !== '' ? $branch : null;
    }

    public function mainBranch(string $path, ?string $preferred = null): ?string
    {
        if ($preferred !== null && $this->revisionExists($path, $preferred)) {
            return $preferred;
        }

        foreach (self::MAIN_BRANCH_CANDIDATES as $branch) {
            if ($this->revisionExists($path, "refs/heads/{$branch}")) {
                return $branch;
            }
        }

        return null;
    }

    public function hasUncommittedChanges(string $path): bool
    {
        $result = $this->git($path, ['status', '--porcelain'], includeErrors: false);

        return $result->successful && trim($result->output) !== '';
    }

    public function checkout(string $path, string $branch): GitResult
    {
        return $this->git($path, ['checkout', $branch]);
    }

    public function createBranch(string $path, string $branch): GitResult
    {
        return $this->git($path, ['checkout', '-b', $branch]);
    }

    public function pull(string $path): GitResult
    {
        return $this->git($path, ['pull']);
    }

    public function commitsOn(string $path, string $date, ?string $author = null): array
    {
        $arguments = ['log', "--since={$date} 00:00:00", "--until={$date} 23:59:59"];

        if ($author !== null) {
            $arguments[] = "--author={$author}";
        }

        $arguments[] = '--pretty=format:%H|%an|%ad|%s';
        $arguments[] = '--date=short';

        $result = $this->git($path, $arguments, includeErrors: false);

        if (! $result->successful) {
            return [];
        }

        $commits = [];

        foreach (explode("\n", trim($result->output)) as $line) {
            $parts = explode('|', $line, 4);

            if (count($parts) === 4) {
                $commits[] = new Commit(...$parts);
            }
        }

        return $commits;
    }

    private function revisionExists(string $path, string $revision): bool
    {
        return $this->git($path, ['rev-parse', '--verify', '--quiet', $revision], includeErrors: false)->successful;
    }

    /**
     * @param list<string> $arguments
     */
    private function git(string $path, array $arguments, bool $includeErrors = true): GitResult
    {
        $command = sprintf(
            'git -C %s %s %s',
            escapeshellarg($path),
            implode(' ', array_map(escapeshellarg(...), $arguments)),
            $includeErrors ? '2>&1' : '2>/dev/null',
        );

        exec($command, $lines, $exitCode);

        return new GitResult($exitCode === 0, implode("\n", $lines));
    }
}
