<?php

namespace App\Console\Commands;

use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use App\Console\Commands\Concerns\AskForPath;
use App\ProjectAnalyzers\PackageProjectAnalyzer;
use App\Services\GitRepository;
use App\Services\CommandExecutor;

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

        // Use search() for project selection
        $selectedProject = $this->search(
            label: 'Select a project to upgrade',
            search: function (string $search) use ($projects): array {
                $filtered = array_filter($projects, function ($project) use ($search) {
                    return stripos($project['name'], $search) !== false ||
                        stripos($project['version'], $search) !== false;
                });

                return array_map(function ($project) {
                    return "{$project['name']} (Nuxt {$project['version']})";
                }, $filtered);
            },
            multiple: false
        );

        if (!$selectedProject) {
            return null;
        }

        // Find the selected project by matching the display string
        foreach ($projects as $project) {
            $displayString = "{$project['name']} (Nuxt {$project['version']})";
            if ($displayString === $selectedProject) {
                return $project;
            }
        }

        return null;
    }

    private function upgradeProject(array $project): void
    {
        $this->console->info("Upgrading {$project['name']}...");
        $this->console->writeln('');

        // Initialize services
        $gitRepo = new GitRepository($project['path']);
        $commandExecutor = new CommandExecutor($this->console);

        // Check for uncommitted changes
        if ($gitRepo->hasUncommittedChanges()) {
            $this->console->error("Uncommitted changes detected. Please commit or stash changes first.");
            return;
        }

        // Determine main branch
        $mainBranch = $gitRepo->determineMainBranch();
        if (!$mainBranch) {
            $this->console->error("Could not determine main branch");
            return;
        }

        $this->console->info("Main branch: {$mainBranch}");

        // Checkout main branch
        $currentBranch = $gitRepo->getCurrentBranch();
        if ($currentBranch !== $mainBranch) {
            $this->console->info("Checking out {$mainBranch}...");
            if (!$gitRepo->checkout($mainBranch)) {
                $this->console->error("Failed to checkout {$mainBranch}");
                return;
            }
        } else {
            $this->console->info("Already on {$mainBranch}");
        }

        // Pull latest changes
        $this->console->info("Pulling latest changes...");
        if (!$gitRepo->pull()) {
            $this->console->error("Pull failed");
            return;
        }

        // Define commands to run
        $commands = $this->getUpgradeCommands($project);

        // Execute commands
        $commandExecutor->executeCommands($commands, $project['path']);

        // Get the new Nuxt version after upgrade
        $packageJsonPath = $project['path'] . '/package.json';
        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $newVersion = $packageJson['dependencies']['nuxt'] ?? $packageJson['devDependencies']['nuxt'] ?? 'unknown';

        // Create a new branch with the version name
        $versionBranchName = "upgrade/nuxt-{$newVersion}";
        $this->console->info("Creating branch: {$versionBranchName}");
        if (!$gitRepo->checkoutNewBranch($versionBranchName)) {
            $this->console->error("Failed to create branch");
            return;
        }

        $this->console->writeln('');
        $this->console->info('✓ Nuxt project upgrade completed.');
        $this->console->info("Branch created: {$versionBranchName}");
    }

    /**
     * Define the array of commands to execute during upgrade
     *
     * @return array Array of command definitions with 'name', 'command', and 'type' keys
     */
    private function getUpgradeCommands(array $project): array
    {
        $version = $project['version'];

        return [
            [
                'name'    => 'Running: npx nuxi upgrade --dedupe',
                'command' => 'npx --yes nuxi upgrade --dedupe',
                'type'    => 'realtime',
            ],
            [
                'name'    => 'Running: npx taze -w',
                'command' => 'npx --yes taze -w',
                'type'    => 'realtime',
            ],
        ];
    }
}
