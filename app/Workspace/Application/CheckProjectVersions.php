<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Workspace\Domain\ComposerProjectDetector;
use App\Workspace\Domain\Git;
use App\Workspace\Domain\NpmProjectDetector;
use App\Workspace\Domain\Project;
use App\Workspace\Domain\ProjectType;
use App\Workspace\Domain\WorkspaceScanner;

final readonly class CheckProjectVersions
{
    public function __construct(
        private WorkspaceScanner $scanner,
        private Git $git,
        private ComposerProjectDetector $composer,
        private NpmProjectDetector $npm,
    ) {
    }

    /**
     * @return list<ProjectVersion>
     */
    public function __invoke(string $workspace): array
    {
        $versions = [];
        $laravelProjects = [];

        foreach ($this->scanner->manifests($workspace, ComposerProjectDetector::MANIFEST) as $manifest) {
            $project = $this->composer->detect($manifest);

            if ($project->is(ProjectType::Laravel)) {
                $laravelProjects[$project->name] = true;
            }

            $versions[] = $this->withBranch($project);
        }

        // A Laravel app's package.json only holds its frontend tooling, so it is already covered above.
        foreach ($this->scanner->manifests($workspace, NpmProjectDetector::MANIFEST) as $manifest) {
            if (isset($laravelProjects[$manifest->projectName()])) {
                continue;
            }

            $versions[] = $this->withBranch($this->npm->detect($manifest));
        }

        return $versions;
    }

    private function withBranch(Project $project): ProjectVersion
    {
        $branch = $this->git->isRepository($project->path) ? $this->git->currentBranch($project->path) : null;

        return new ProjectVersion($project, $branch);
    }
}
