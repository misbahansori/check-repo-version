<?php

declare(strict_types=1);

namespace App\Workspace\Infrastructure;

use App\Workspace\Domain\Manifest;
use App\Workspace\Domain\WorkspaceScanner;
use Tempest\Container\Autowire;

#[Autowire]
final readonly class FilesystemWorkspaceScanner implements WorkspaceScanner
{
    public function repositories(string $workspace): array
    {
        return array_map(dirname(...), glob("{$workspace}/*/.git", GLOB_ONLYDIR) ?: []);
    }

    public function manifests(string $workspace, string $fileName): array
    {
        $manifests = [];

        foreach (glob("{$workspace}/*/{$fileName}") ?: [] as $file) {
            $manifest = $this->readManifest(dirname($file), $fileName);

            if ($manifest !== null) {
                $manifests[] = $manifest;
            }
        }

        return $manifests;
    }

    public function readManifest(string $projectPath, string $fileName): ?Manifest
    {
        $contents = @file_get_contents("{$projectPath}/{$fileName}");

        if ($contents === false) {
            return null;
        }

        $data = json_decode($contents, associative: true);

        return new Manifest($projectPath, is_array($data) ? $data : []);
    }
}
