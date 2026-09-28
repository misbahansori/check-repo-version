<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Workspace\Domain\Commit;

final readonly class RepositoryCommits
{
    /**
     * @param list<Commit> $commits
     */
    public function __construct(
        public string $repository,
        public array $commits,
    ) {
    }
}
