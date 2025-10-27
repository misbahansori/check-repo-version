<?php

namespace App\Services;

final readonly class GitRepository
{
    public function __construct(
        private string $repositoryPath
    ) {}

    /**
     * Get the current git branch
     */
    public function getCurrentBranch(): string
    {
        $branchOutput = shell_exec("cd {$this->repositoryPath} && git branch --show-current");
        return $branchOutput !== null ? trim($branchOutput) : 'unknown';
    }

    /**
     * Determine the main branch of the repository
     * Checks for common branch names: main, master, develop, trunk
     */
    public function determineMainBranch(): ?string
    {
        $commonBranches = ['main', 'master', 'develop', 'trunk'];
        foreach ($commonBranches as $branch) {
            $result = shell_exec("cd {$this->repositoryPath} && git branch --list {$branch}");
            if ($result !== null && str_contains($result, $branch)) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * Check if the repository has uncommitted changes
     */
    public function hasUncommittedChanges(): bool
    {
        $status = shell_exec("cd {$this->repositoryPath} && git status --porcelain");
        return $status !== null && trim($status) !== '';
    }

    /**
     * Checkout a branch
     */
    public function checkout(string $branch): bool
    {
        $output = shell_exec("cd {$this->repositoryPath} && git checkout {$branch} 2>&1");
        return !str_contains(strtolower($output), 'error');
    }

    /**
     * Pull latest changes from remote
     */
    public function pull(): bool
    {
        $output = shell_exec("cd {$this->repositoryPath} && git pull 2>&1");
        return !str_contains(strtolower($output), 'error') && !str_contains(strtolower($output), 'fatal');
    }

    /**
     * Create and checkout a new branch
     */
    public function checkoutNewBranch(string $branchName): bool
    {
        $output = shell_exec("cd {$this->repositoryPath} && git checkout -b {$branchName} 2>&1");
        return !str_contains(strtolower($output), 'error');
    }
}
