<?php

namespace App\Console\Commands;

use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use App\Console\Commands\Concerns\AskForPath;
use App\ProjectAnalyzers\PackageProjectAnalyzer;

final readonly class UpgradeNuxtProjectCommand
{
    use HasConsole;
    use AskForPath;

    private PackageProjectAnalyzer $analyzer;

    public function __construct()
    {
        $this->analyzer = new PackageProjectAnalyzer();
    }

    #[ConsoleCommand(name: 'nuxt:upgrade')]
    public function upgrade(bool $cache = true)
    {
        $path = $this->askForPath(shouldUseCache: $cache);

        if (!$path) {
            $this->console->error("⚠️  Path: {$path} is invalid");
            return ExitCode::INVALID;
        }

        $this->console->info("Scanning for Nuxt projects in: {$path}");

        $nuxtProjects = $this->findNuxtProjects($path);

        if (empty($nuxtProjects)) {
            $this->console->error("No Nuxt projects found in {$path}");
            return ExitCode::INVALID;
        }

        $selectedProject = $this->selectProject($nuxtProjects);

        if (!$selectedProject) {
            $this->console->info("No project selected");
            return ExitCode::SUCCESS;
        }

        $this->upgradeProject($selectedProject);
    }

    private function findNuxtProjects(string $path): array
    {
        $packageFiles = glob($path . '/**/package.json', GLOB_BRACE);
        $nuxtProjects = [];

        foreach ($packageFiles as $file) {
            try {
                $result = $this->analyzer->analyze($file);
                if ($result['type'] === 'nuxt') {
                    $nuxtProjects[] = [
                        'path' => dirname($file),
                        'name' => $result['project'],
                        'version' => $result['version'],
                    ];
                }
            } catch (\Exception $e) {
                // Skip files that can't be analyzed
                continue;
            }
        }

        return $nuxtProjects;
    }

    private function selectProject(array $projects): ?array
    {
        if (count($projects) === 1) {
            $this->console->info("Found 1 Nuxt project: {$projects[0]['name']}");
            return $projects[0];
        }

        $this->console->info("Found " . count($projects) . " Nuxt projects");

        // Use search() to allow user to filter and select
        $selected = $this->console->search(
            label: 'Select a project to upgrade',
            search: function (string $query) use ($projects): array {
                // Filter projects based on search query
                $filtered = array_filter($projects, function ($project) use ($query) {
                    return str_contains(strtolower($project['name']), strtolower($query));
                });

                // Format for search results display
                $results = [];
                foreach ($filtered as $project) {
                    $results[$project['name']] = "{$project['name']} (Nuxt {$project['version']})";
                }

                return $results;
            }
        );

        if (!$selected) {
            return null;
        }

        // Find the selected project by name
        foreach ($projects as $project) {
            if ($project['name'] === $selected) {
                return $project;
            }
        }

        return null;
    }

    private function upgradeProject(array $project): void
    {
        $this->console->info("Upgrading {$project['name']}...");
        $this->console->writeln('');

        $originalDir = getcwd();
        chdir($project['path']);

        // Run npx nuxi upgrade --dedupe
        $this->console->info("Running: npx nuxi upgrade --dedupe");
        $this->console->writeln('');

        $output = [];
        $returnCode = 0;
        exec('npx nuxi upgrade --dedupe 2>&1', $output, $returnCode);

        $this->displayCommandOutput($output, $returnCode);

        $this->console->writeln('');

        // Run npx taze -w
        $this->console->info("Running: npx taze -w");
        $this->console->writeln('');

        $output = [];
        $returnCode = 0;
        exec('npx taze -w 2>&1', $output, $returnCode);

        $this->displayCommandOutput($output, $returnCode);

        chdir($originalDir);

        $this->console->writeln('');
        $this->console->info('✓ Nuxt project upgrade completed.');
    }

    private function displayCommandOutput(array $output, int $returnCode): void
    {
        foreach ($output as $line) {
            $this->console->writeln("  {$line}");
        }

        if ($returnCode !== 0) {
            $this->console->error("Command exited with code: {$returnCode}");
        } else {
            $this->console->info("✓ Command completed successfully");
        }
    }
}
