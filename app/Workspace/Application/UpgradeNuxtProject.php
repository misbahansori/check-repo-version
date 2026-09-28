<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Shared\Application\Progress;
use App\Workspace\Domain\Git;
use App\Workspace\Domain\NpmProjectDetector;
use App\Workspace\Domain\Project;
use App\Workspace\Domain\ProjectType;
use App\Workspace\Domain\WorkspaceScanner;

final readonly class UpgradeNuxtProject
{
    /** Step label => command, run in order inside the project. */
    private const array UPGRADE_STEPS = [
        'Running: npx nuxi upgrade --dedupe' => 'npx --yes nuxi upgrade --dedupe',
        'Running: npx taze -w' => 'npx --yes taze -w',
    ];

    public function __construct(
        private WorkspaceScanner $scanner,
        private NpmProjectDetector $npm,
        private Git $git,
        private CommandRunner $runner,
        private Progress $progress,
    ) {
    }

    /**
     * @return list<Project>
     */
    public function findProjects(string $workspace): array
    {
        $projects = [];

        foreach ($this->scanner->manifests($workspace, NpmProjectDetector::MANIFEST) as $manifest) {
            $project = $this->npm->detect($manifest);

            if ($project->is(ProjectType::Nuxt)) {
                $projects[] = $project;
            }
        }

        return $projects;
    }

    /**
     * Pull the project's main branch, upgrade its dependencies and leave the
     * result on a new `upgrade/nuxt-<version>` branch.
     */
    public function upgrade(Project $project): void
    {
        $this->progress->info("Upgrading {$project->name}...");
        $this->progress->writeln();

        if ($this->git->hasUncommittedChanges($project->path)) {
            $this->progress->error('Uncommitted changes detected. Please commit or stash changes first.');

            return;
        }

        $mainBranch = $this->git->mainBranch($project->path);

        if ($mainBranch === null) {
            $this->progress->error('Could not determine main branch');

            return;
        }

        $this->progress->info("Main branch: {$mainBranch}");

        if ($this->git->currentBranch($project->path) !== $mainBranch) {
            $this->progress->info("Checking out {$mainBranch}...");

            if (! $this->git->checkout($project->path, $mainBranch)->successful) {
                $this->progress->error("Failed to checkout {$mainBranch}");

                return;
            }
        } else {
            $this->progress->info("Already on {$mainBranch}");
        }

        $this->progress->info('Pulling latest changes...');

        if (! $this->git->pull($project->path)->successful) {
            $this->progress->error('Pull failed');

            return;
        }

        foreach (self::UPGRADE_STEPS as $label => $command) {
            $this->progress->writeln();
            $this->progress->info($label);
            $this->progress->writeln();

            $started = $this->runner->run($command, $project->path, fn (string $line) => $this->progress->writeln("  {$line}"));

            if (! $started) {
                $this->progress->error("Failed to execute command: {$command}");
            }
        }

        $branch = 'upgrade/nuxt-' . str_replace('^', '', $this->upgradedVersion($project));
        $this->progress->info("Creating branch: {$branch}");

        if (! $this->git->createBranch($project->path, $branch)->successful) {
            $this->progress->error('Failed to create branch');

            return;
        }

        $this->progress->writeln();
        $this->progress->info('✓ Nuxt project upgrade completed.');
        $this->progress->info("Branch created: {$branch}");
    }

    private function upgradedVersion(Project $project): string
    {
        $manifest = $this->scanner->readManifest($project->path, NpmProjectDetector::MANIFEST);

        return ($manifest !== null ? $this->npm->detect($manifest)->version : null) ?? 'unknown';
    }
}
