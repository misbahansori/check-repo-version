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

        // Build options array for selection
        $options = [];
        foreach ($projects as $project) {
            $options[] = "{$project['name']} (Nuxt {$project['version']})";
        }

        // Use ask() with options for selection
        $choice = $this->ask(
            question: 'Select a project to upgrade',
            options: $options
        );

        if (!$choice) {
            return null;
        }

        // Find the selected project by index
        $index = intval($choice);
        if ($index >= 0 && $index < count($projects)) {
            return $projects[$index];
        }

        return null;
    }

    private function upgradeProject(array $project): void
    {
        $this->console->info("Upgrading {$project['name']}...");
        $this->console->writeln('');

        $originalDir = getcwd();
        chdir($project['path']);

        // Check for uncommitted changes
        if ($this->hasUncommittedChanges()) {
            $this->console->error("Uncommitted changes detected. Please commit or stash changes first.");
            chdir($originalDir);
            return;
        }

        // Determine main branch
        $mainBranch = $this->determineMainBranch();
        if (!$mainBranch) {
            $this->console->error("Could not determine main branch");
            chdir($originalDir);
            return;
        }

        $this->console->info("Main branch: {$mainBranch}");

        // Checkout main branch
        $currentBranch = $this->getCurrentBranch();
        if ($currentBranch !== $mainBranch) {
            $this->console->info("Checking out {$mainBranch}...");
            $output = shell_exec("git checkout {$mainBranch} 2>&1");
            if (str_contains(strtolower($output), 'error')) {
                $this->console->error("Failed to checkout {$mainBranch}: {$output}");
                chdir($originalDir);
                return;
            }
        } else {
            $this->console->info("Already on {$mainBranch}");
        }

        // Pull latest changes
        $this->console->info("Pulling latest changes...");
        $output = shell_exec("git pull 2>&1");
        if (str_contains(strtolower($output), 'error') || str_contains(strtolower($output), 'fatal')) {
            $this->console->error("Pull failed: {$output}");
            chdir($originalDir);
            return;
        }

        // Get the new Nuxt version after upgrade
        $this->console->writeln('');

        // Run npx nuxi upgrade --dedupe
        $this->console->info("Running: npx nuxi upgrade --dedupe");
        $this->console->writeln('');

        $this->runCommandRealtime('npx --yes nuxi upgrade --dedupe');

        // Read the updated package.json to get the new version
        $packageJsonPath = $project['path'] . '/package.json';
        $packageJson = json_decode(file_get_contents($packageJsonPath), true);
        $newVersion = $packageJson['dependencies']['nuxt'] ?? $packageJson['devDependencies']['nuxt'] ?? 'unknown';

        $this->console->writeln('');
        $this->console->info("New Nuxt version: {$newVersion}");

        // Create a new branch with the version name
        $versionBranchName = "upgrade/nuxt-{$newVersion}";
        $this->console->info("Creating branch: {$versionBranchName}");
        $output = shell_exec("git checkout -b {$versionBranchName} 2>&1");
        if (str_contains(strtolower($output), 'error')) {
            $this->console->error("Failed to create branch: {$output}");
            chdir($originalDir);
            return;
        }

        $this->console->writeln('');

        // Run npx taze -w
        $this->console->info("Running: npx taze -w");
        $this->console->writeln('');

        $this->runCommandRealtime('npx taze -w');

        chdir($originalDir);

        $this->console->writeln('');
        $this->console->info('✓ Nuxt project upgrade completed.');
        $this->console->info("Branch created: {$versionBranchName}");
    }

    private function runCommandRealtime(string $command): void
    {
        $process = popen($command . ' 2>&1', 'r');

        if (!$process) {
            $this->console->error("Failed to execute command: {$command}");
            return;
        }

        while (!feof($process)) {
            $line = fgets($process);
            if ($line !== false && trim($line) !== '') {
                $this->console->writeln("  " . trim($line));
            }
        }

        pclose($process);
    }

    private function displayCommandOutput(?string $output): void
    {
        if ($output && trim($output) !== '') {
            $lines = explode("\n", trim($output));
            foreach ($lines as $line) {
                if (trim($line) !== '') {
                    $this->console->writeln("  {$line}");
                }
            }
        }
    }

    private function getCurrentBranch(): string
    {
        $branchOutput = shell_exec("git branch --show-current");
        return $branchOutput !== null ? trim($branchOutput) : 'unknown';
    }

    private function determineMainBranch(): ?string
    {
        // Check common main branch names
        $commonBranches = ['main', 'master', 'develop', 'trunk'];
        foreach ($commonBranches as $branch) {
            $result = shell_exec("git branch --list {$branch}");
            if ($result !== null && str_contains($result, $branch)) {
                return $branch;
            }
        }

        // No main branch found
        return null;
    }

    private function hasUncommittedChanges(): bool
    {
        $status = shell_exec('git status --porcelain');
        return $status !== null && trim($status) !== '';
    }
}
