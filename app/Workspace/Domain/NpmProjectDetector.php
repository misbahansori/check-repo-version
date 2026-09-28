<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

final readonly class NpmProjectDetector
{
    public const string MANIFEST = 'package.json';

    private const array FRAMEWORKS = [
        'nuxt' => ProjectType::Nuxt,
        'next' => ProjectType::Next,
        'vue' => ProjectType::Vue,
    ];

    public function detect(Manifest $manifest): Project
    {
        $dependencies = $manifest->section('dependencies');
        $devDependencies = $manifest->section('devDependencies');

        foreach (self::FRAMEWORKS as $package => $type) {
            $version = $dependencies[$package] ?? $devDependencies[$package] ?? null;

            if ($version !== null) {
                return new Project($manifest->projectName(), $manifest->projectPath, $type, $version);
            }
        }

        return new Project($manifest->projectName(), $manifest->projectPath, ProjectType::Unknown, null);
    }
}
