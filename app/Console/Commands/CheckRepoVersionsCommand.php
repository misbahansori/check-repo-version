<?php

namespace App\Console\Commands;

use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;
use App\ProjectAnalyzers\PackageProjectAnalyzer;
use App\ProjectAnalyzers\ComposerProjectAnalyzer;

final readonly class CheckRepoVersionsCommand
{
    use HasConsole;

    private array $analyzers;

    public function __construct()
    {
        $this->analyzers = [
            'composer.json' => new ComposerProjectAnalyzer(),
            'package.json' => new PackageProjectAnalyzer()
        ];
    }

    #[ConsoleCommand(name: 'repo:check')]
    public function check(string $path): void
    {
        $path = $this->normalizePath($path);

        if (!$this->validatePath($path)) {
            return;
        }

        $projectFiles = $this->scanForProjectFiles($path);

        if (empty($projectFiles)) {
            $this->writeln("<error>No project files found in {$path}</error>");
            return;
        }

        $results = $this->analyzeProjects($projectFiles);

        $this->displayResults($results);
    }

    private function normalizePath(string $path): string
    {
        return str_replace('~', $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'], $path);
    }

    private function validatePath(string $path): bool
    {
        if (!is_dir($path)) {
            $this->writeln("<error>Path not found: {$path}</error>");
            return false;
        }

        $this->writeln("<comment>Scanning: {$path}</comment>");
        return true;
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
        $this->writeln('<h1>Repository versions:</h1>');

        $this->displayTable(
            $results,
            ['Project', 'Type', 'Version', 'Branch']
        );

        $this->writeln('');
        $this->writeln('<comment>Repository versions check completed.</comment>');
    }

    private function displayTable(array $rows, array $headers): void
    {
        $columnWidths = $this->calculateColumnWidths($rows, $headers);
        $borderLine = $this->createBorderLine($columnWidths);

        // Display table with borders
        $this->writeln($borderLine);
        $this->displayTableHeaders($headers, $columnWidths);
        $this->writeln($this->createBorderLine($columnWidths, '=', '+'));
        $this->displayTableRows($rows, $columnWidths);
        $this->writeln($borderLine);
    }

    private function calculateColumnWidths(array $rows, array $headers): array
    {
        $columnWidths = [];

        // Initialize with header lengths
        foreach ($headers as $index => $header) {
            $columnWidths[$index] = strlen($header) + 2;
        }

        // Adjust for content lengths
        foreach ($rows as $row) {
            foreach (array_values($row) as $index => $value) {
                $valueLength = strlen((string)$value);
                if (isset($columnWidths[$index])) {
                    $columnWidths[$index] = max($columnWidths[$index], $valueLength + 2);
                }
            }
        }

        return $columnWidths;
    }

    private function createBorderLine(array $columnWidths, string $char = '-', string $intersection = '+'): string
    {
        $line = $intersection;
        foreach ($columnWidths as $width) {
            $line .= str_repeat($char, $width + 2) . $intersection;
        }
        return $line;
    }

    private function displayTableHeaders(array $headers, array $columnWidths): void
    {
        $headerLine = '|';
        foreach ($headers as $index => $header) {
            $paddedHeader = ' ' . str_pad($header, $columnWidths[$index]) . ' ';
            $headerLine .= "<strong>{$paddedHeader}</strong>|";
        }
        $this->writeln($headerLine);
    }

    private function displayTableRows(array $rows, array $columnWidths): void
    {
        foreach ($rows as $row) {
            $line = '|';
            $rowValues = array_values($row);

            foreach ($rowValues as $index => $value) {
                $paddedValue = ' ' . str_pad((string)$value, $columnWidths[$index]) . ' ';
                $line .= $this->formatTableCell($value, $paddedValue) . '|';
            }

            $this->writeln($line);
        }
    }

    private function formatTableCell($value, string $paddedValue): string
    {
        if ($value === null) {
            return "<comment>{$paddedValue}</comment>";
        } elseif (!empty($value)) {
            return "<em>{$paddedValue}</em>";
        }

        return $paddedValue;
    }
}
