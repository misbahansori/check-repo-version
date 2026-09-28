<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Shared\Application\Progress;
use App\Workspace\Domain\Git;

final readonly class PullRepositories
{
    public function __construct(
        private Git $git,
        private Progress $progress,
    ) {
    }

    /**
     * Switch every clean repository to its main branch and pull it.
     *
     * @param list<string> $repositories
     * @return list<PullOutcome>
     */
    public function __invoke(array $repositories, ?string $preferredMainBranch = null): array
    {
        $outcomes = [];

        foreach ($repositories as $index => $path) {
            $this->progress->info('[' . ($index + 1) . '/' . count($repositories) . '] ' . basename($path));

            $outcomes[] = $this->pull($path, $preferredMainBranch);
        }

        return $outcomes;
    }

    private function pull(string $path, ?string $preferredMainBranch): PullOutcome
    {
        $name = basename($path);
        $currentBranch = $this->git->currentBranch($path) ?? 'unknown';
        $this->progress->info("  Current branch: <em>{$currentBranch}</em>");

        $mainBranch = $this->git->mainBranch($path, $preferredMainBranch);

        if ($mainBranch === null) {
            $this->progress->error('No main branch found');

            return new PullOutcome($name, $currentBranch, PullStatus::Error, 'No main branch found');
        }

        $this->progress->info("  Main branch: <em>{$mainBranch}</em>");

        if ($this->git->hasUncommittedChanges($path)) {
            $this->progress->error('Uncommitted changes detected');

            return new PullOutcome($name, $currentBranch, PullStatus::Skipped, 'Uncommitted changes detected');
        }

        if ($currentBranch !== $mainBranch) {
            $this->progress->info("  Switching to {$mainBranch}...");
            $checkout = $this->git->checkout($path, $mainBranch);

            if (! $checkout->successful) {
                $this->progress->error('Failed to switch branch');
                $this->progress->error("  {$checkout->output}");

                return new PullOutcome($name, $currentBranch, PullStatus::Error, "Failed to switch to {$mainBranch}: {$checkout->output}");
            }
        } else {
            $this->progress->info("  Already on main branch {$mainBranch}");
        }

        $this->progress->info('  Pulling latest changes...');
        $pull = $this->git->pull($path);

        if (! $pull->successful) {
            $this->progress->error('Pull failed');
            $this->progress->error("  {$pull->output}");

            return new PullOutcome($name, $mainBranch, PullStatus::Error, "Pull failed: {$pull->output}");
        }

        $this->progress->info('Pull successful');

        return new PullOutcome($name, $mainBranch, PullStatus::Success, $pull->output);
    }
}
