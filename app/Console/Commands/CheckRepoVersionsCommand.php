<?php

namespace App\Console\Commands;

use Tempest\Cache\Cache;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use App\Console\Commands\Concerns\AskForPath;
use App\Console\Commands\Concerns\ConsoleTable;
use App\ProjectAnalyzers\PackageProjectAnalyzer;
use App\ProjectAnalyzers\ComposerProjectAnalyzer;

final readonly class CheckRepoVersionsCommand
{
    use HasConsole;
    use AskForPath;
    use ConsoleTable;

    private array $analyzers;

    public function __construct()
    {
        $this->analyzers = [
            'composer.json' => new ComposerProjectAnalyzer(),
            'package.json' => new PackageProjectAnalyzer()
        ];
    }

    #[ConsoleCommand(name: 'repo:check')]
    public function check(bool $cache =  true)
    {
        $path = $this->askForPath(shouldUseCache: $cache);

        if (!$path) {
            $this->console->error("⚠️  Path: {$path} is invalid ");
            return ExitCode::INVALID;
        }

        $this->console->info("Scanning: {$path}");

        $projectFiles = $this->scanForProjectFiles($path);

        if (empty($projectFiles)) {
            $this->console->error("No project files found in {$path}");
            return;
        }

        $results = $this->analyzeProjects($projectFiles);

        $this->displayResults($results);
    }

    private function scanForProjectFiles(string $path): array
    {
        $allFiles = [];

        foreach (array_keys($this->analyzers) as $filePattern) {
            $files = glob($path . '/**/' . $filePattern, GLOB_BRACE);
            if (!empty($files)) {
                $allFiles[$filePattern] = $files;
            }
        }

        return $allFiles;
    }

    /**
     * Analyze projects found in the scan
     */
    private function analyzeProjects(array $projectFiles): array
    {
        $results = [];
        $analyzedProjects = [];

        // First analyze all PHP/Composer projects
        if (isset($projectFiles['composer.json'])) {
            foreach ($projectFiles['composer.json'] as $file) {
                $projectPath = dirname($file);
                $projectResult = $this->analyzers['composer.json']->analyze($file);

                // Add git branch information
                $projectResult['branch'] = $this->getGitBranch($projectPath);

                // Keep track of analyzed projects and their types
                $projectName = basename($projectPath);
                $analyzedProjects[$projectName] = $projectResult['type'];

                $results[] = $projectResult;
            }
        }

        // Then only analyze JavaScript/Package projects if they haven't been identified as Laravel
        if (isset($projectFiles['package.json'])) {
            foreach ($projectFiles['package.json'] as $file) {
                $projectPath = dirname($file);
                $projectName = basename($projectPath);

                // Skip package.json analysis if project is already identified as Laravel
                if (isset($analyzedProjects[$projectName]) && $analyzedProjects[$projectName] === 'laravel') {
                    continue;
                }

                $projectResult = $this->analyzers['package.json']->analyze($file);
                $projectResult['branch'] = $this->getGitBranch($projectPath);
                $results[] = $projectResult;
            }
        }

        return $results;
    }

    /**
     * Get the current git branch for a project path
     */
    private function getGitBranch(string $path): ?string
    {
        // Check if directory is a git repository
        if (!is_dir($path . '/.git')) {
            return null;
        }

        // Run git command to get current branch
        $currentDir = getcwd();
        chdir($path);
        $branch = trim(shell_exec('git branch --show-current 2>/dev/null') ?? '');
        chdir($currentDir);

        return $branch ?: null;
    }

    private function displayResults(array $results): void
    {
        $this->console->info('Repository versions:');

        $this->table(
            headers: ['Project', 'Type', 'Version', 'Branch'],
            rows: $results,
        );

        $this->console->info('Repository versions check completed.');
    }
}
