<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Workspace\Domain\Git;

final readonly class ListCommits
{
    public function __construct(
        private Git $git,
    ) {
    }

    /**
     * @param list<string> $repositories
     * @return list<RepositoryCommits> Only repositories that have commits on the date.
     */
    public function __invoke(array $repositories, string $date, ?string $author = null): array
    {
        $results = [];

        foreach ($repositories as $path) {
            $commits = $this->git->commitsOn($path, $date, $author);

            if ($commits !== []) {
                $results[] = new RepositoryCommits(basename($path), $commits);
            }
        }

        return $results;
    }
}
