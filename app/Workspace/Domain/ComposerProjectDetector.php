<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

final readonly class ComposerProjectDetector
{
    public const string MANIFEST = 'composer.json';

    private const array FRAMEWORKS = [
        'laravel/framework' => ProjectType::Laravel,
        'symfony/framework-bundle' => ProjectType::Symfony,
    ];

    public function detect(Manifest $manifest): Project
    {
        $require = $manifest->section('require');

        foreach (self::FRAMEWORKS as $package => $type) {
            if (isset($require[$package])) {
                return new Project($manifest->projectName(), $manifest->projectPath, $type, $require[$package]);
            }
        }

        return new Project($manifest->projectName(), $manifest->projectPath, ProjectType::Unknown, null);
    }
}
