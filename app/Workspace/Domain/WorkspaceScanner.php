<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

interface WorkspaceScanner
{
    /**
     * @return list<string> Paths of the git repositories directly inside the workspace.
     */
    public function repositories(string $workspace): array;

    /**
     * @return list<Manifest> Every `$fileName` manifest directly inside the workspace's projects.
     */
    public function manifests(string $workspace, string $fileName): array;

    public function readManifest(string $projectPath, string $fileName): ?Manifest;
}
