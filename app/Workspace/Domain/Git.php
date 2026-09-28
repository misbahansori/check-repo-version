<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

interface Git
{
    public function isRepository(string $path): bool;

    public function currentBranch(string $path): ?string;

    /**
     * The branch to treat as main: `$preferred` when it exists, otherwise the first
     * of main, master, develop or trunk that exists locally.
     */
    public function mainBranch(string $path, ?string $preferred = null): ?string;

    public function hasUncommittedChanges(string $path): bool;

    public function checkout(string $path, string $branch): GitResult;

    public function createBranch(string $path, string $branch): GitResult;

    public function pull(string $path): GitResult;

    /**
     * @return list<Commit>
     */
    public function commitsOn(string $path, string $date, ?string $author = null): array;
}
