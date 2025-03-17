<?php

namespace App\ProjectAnalyzers;

use App\ProjectAnalyzers\ProjectAnalyzerInterface;

class PackageProjectAnalyzer implements ProjectAnalyzerInterface
{
    public function analyze(string $filePath): array
    {
        $packageJson = json_decode(file_get_contents($filePath), true);
        $projectName = basename(dirname($filePath));

        $dependencies = $packageJson['dependencies'] ?? [];
        $devDependencies = $packageJson['devDependencies'] ?? [];

        $projectType = 'unknown';
        $version = null;

        // Check for Nuxt
        if (isset($dependencies['nuxt']) || isset($devDependencies['nuxt'])) {
            $projectType = 'nuxt';
            $version = $dependencies['nuxt'] ?? $devDependencies['nuxt'];
        }

        // Check for Next.js (extend for more frameworks)
        elseif (isset($dependencies['next']) || isset($devDependencies['next'])) {
            $projectType = 'nextjs';
            $version = $dependencies['next'] ?? $devDependencies['next'];
        }

        // Check for Vue
        elseif (isset($dependencies['vue']) || isset($devDependencies['vue'])) {
            $projectType = 'vue';
            $version = $dependencies['vue'] ?? $devDependencies['vue'];
        }

        return [
            'project' => $projectName,
            'type' => $projectType,
            'version' => $version,
        ];
    }
}
