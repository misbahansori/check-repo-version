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

            $results[] = [
                'project' => $projectName,
                'type' => 'nuxt',
                'version' => $version,
            ];
        }

        // Display results with formatted text
        $this->writeln('<h2>Repository Versions</h2>');
        $this->writeln('');

        // Calculate column widths based on content
        $projectMaxLength = 15;
        $typeMaxLength = 10;

        foreach ($results as $result) {
            $projectMaxLength = max($projectMaxLength, strlen($result['project']));
            $typeMaxLength = max($typeMaxLength, strlen($result['type']));
        }

        // Add padding for readability
        $projectMaxLength += 2;
        $typeMaxLength += 2;

        // Headers with correctly aligned columns
        $projectText = str_pad('Project', $projectMaxLength);
        $typeText = str_pad('Type', $typeMaxLength);
        $this->writeln("<strong>{$projectText}</strong><strong>{$typeText}</strong><strong>Version</strong>");

        // Separator line matching the column widths
        $separator = str_repeat('-', $projectMaxLength + $typeMaxLength + 20);
        $this->writeln($separator);

        // Data rows with improved formatting
        foreach ($results as $result) {
            $project = str_pad($result['project'], $projectMaxLength);
            $type = str_pad($result['type'], $typeMaxLength);
            $version = $result['version'] ?? 'Not found';

            if ($result['version'] === null) {
                $this->writeln("{$project}{$type}<error>{$version}</error>");
            } else {
                $this->writeln("{$project}{$type}<success>{$version}</success>");
            }
        }

        $this->writeln('');
        $this->writeln('<comment>Repository versions check completed.</comment>');
    }
}
