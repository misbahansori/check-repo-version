<?php

namespace App\Console\Commands;

use Tempest\Console\HasConsole;
use Tempest\Console\ConsoleCommand;

final readonly class CheckRepoVersionsCommand
{
    use HasConsole;

    #[ConsoleCommand(name: 'repo:check-versions')]
    public function check()
    {
        $path = $this->ask('Enter the path to the repository', [
            '~/gilgamesh/',
        ]);
        // Properly expand the tilde character for home directory
        $path = str_replace('~', $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'], $path);

        // Make sure path exists
        if (!is_dir($path)) {
            $this->writeln("<error>Path not found: {$path}</error>");
            return;
        }

        $this->writeln("<comment>Scanning: {$path}</comment>");

        // scan the path for git composer.json or package.json files and get the contents
        $composerJsonFiles = glob($path . '/**/composer.json', GLOB_BRACE);
        $packageJsonFiles = glob($path . '/**/package.json', GLOB_BRACE);

        // Remove the debug statement that halts execution
        // dd($composerJsonFiles);

        if (empty($composerJsonFiles) && empty($packageJsonFiles)) {
            $this->writeln("<error>No composer.json or package.json files found in {$path}</error>");
            return;
        }

        $results = [];

        foreach ($composerJsonFiles as $composerJsonFile) {
            $composerJson = json_decode(file_get_contents($composerJsonFile), true);

            // get the laravel version from the require key
            $version = $composerJson['require']['laravel/framework'] ?? null;
            $projectName = basename(dirname($composerJsonFile));

            $results[] = [
                'project' => $projectName,
                'type' => 'laravel',
                'version' => $version,
            ];
        }

        // check package.json files for nuxt version
        foreach ($packageJsonFiles as $packageJsonFile) {
            $packageJson = json_decode(file_get_contents($packageJsonFile), true);

            // undefined array key nuxt
            // $version = $packageJson['dependencies']['nuxt'] ?? $packageJson['devDependencies']['nuxt'];

            // get the nuxt version from the package.json file
            $dependencies = $packageJson['dependencies'] ?? [];
            $devDependencies = $packageJson['devDependencies'] ?? [];

            $version = $dependencies['nuxt'] ?? $devDependencies['nuxt'] ?? null;
            $projectName = basename(dirname($packageJsonFile));

            // Determine project type - if nuxt isn't in dependencies, mark as unknown
            $projectType = 'unknown';
            if (isset($dependencies['nuxt']) || isset($devDependencies['nuxt'])) {
                $projectType = 'nuxt';
            }

            $results[] = [
                'project' => $projectName,
                'type' => $projectType,
                'version' => $version,
            ];
        }

        $this->writeln('<h1>Repository versions:</h1>');

        // Use the generic table display method
        $this->displayTable(
            $results,
            ['Project', 'Type', 'Version']
        );

        $this->writeln('');
        $this->writeln('<comment>Repository versions check completed.</comment>');
    }

    /**
     * Display results in a formatted table with borders
     *
     * @param array $rows The data rows to display
     * @param array $headers The column headers
     */
    private function displayTable(array $rows, array $headers): void
    {
        // Calculate column widths based on content
        $columnWidths = [];

        // Initialize with header lengths
        foreach ($headers as $index => $header) {
            $columnWidths[$index] = strlen($header) + 2; // Add padding
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

        // Create border line function
        $createBorderLine = function ($char = '-', $intersection = '+') use ($columnWidths) {
            $line = $intersection;
            foreach ($columnWidths as $width) {
                $line .= str_repeat($char, $width + 2) . $intersection;
            }
            return $line;
        };

        // Top border
        $this->writeln($createBorderLine());

        // Headers with borders
        $headerLine = '|';
        foreach ($headers as $index => $header) {
            $paddedHeader = ' ' . str_pad($header, $columnWidths[$index]) . ' ';
            $headerLine .= "<strong>{$paddedHeader}</strong>|";
        }
        $this->writeln($headerLine);

        // Middle border
        $this->writeln($createBorderLine('=', '+'));

        // Data rows with borders
        foreach ($rows as $row) {
            $line = '|';
            $rowValues = array_values($row);

            foreach ($rowValues as $index => $value) {
                $paddedValue = ' ' . str_pad((string)$value, $columnWidths[$index]) . ' ';

                // Apply more subtle formatting without background colors
                if ($value === null) {
                    $line .= "<comment>{$paddedValue}</comment>|";
                } elseif (!empty($value)) {
                    $line .= "<em>{$paddedValue}</em>|";
                } else {
                    $line .= $paddedValue . '|';
                }
            }

            $this->writeln($line);
        }

        // Bottom border
        $this->writeln($createBorderLine());
    }
}
