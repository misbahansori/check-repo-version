<?php

namespace App\ProjectAnalyzers;

use App\ProjectAnalyzers\ProjectAnalyzerInterface;

class ComposerProjectAnalyzer implements ProjectAnalyzerInterface
{
    public function analyze(string $filePath): array
    {
        $composerJson = json_decode(file_get_contents($filePath), true);
        $projectName = basename(dirname($filePath));

        $projectType = 'unknown';
        $version = null;

        // Check for Laravel
        if (isset($composerJson['require']['laravel/framework'])) {
            $projectType = 'laravel';
            $version = $composerJson['require']['laravel/framework'];
        }

        // Check for Symfony (extend for more frameworks)
        elseif (isset($composerJson['require']['symfony/framework-bundle'])) {
            $projectType = 'symfony';
            $version = $composerJson['require']['symfony/framework-bundle'];
        }

        return [
            'project' => $projectName,
            'type' => $projectType,
            'version' => $version,
        ];
    }
}
