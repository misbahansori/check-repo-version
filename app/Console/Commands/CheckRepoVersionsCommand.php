<?php

namespace App\Console\Commands;

use Tempest\Console\ConsoleCommand;
use Tempest\Console\HasConsole;

final readonly class CheckRepoVersionsCommand
{
    use HasConsole;

    #[ConsoleCommand(name: 'repo:check-versions')]
    public function check(string $path)
    {
        // ~/gilgamesh/
        $path = realpath($path);

        // scan the path for git composer.json or package.json files and get the contents
        $composerJsonFiles = glob($path . '/**/composer.json', GLOB_BRACE);
        $packageJsonFiles = glob($path . '/**/package.json', GLOB_BRACE);

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

        // Create csv file with the results
        $csv = fopen('results.csv', 'w');
        fputcsv($csv, ['Project', 'Version']);

        foreach ($results as $result) {
            fputcsv($csv, $result);
        }

        fclose($csv);

        $this->writeln('Results saved to results.csv');
    }
}
