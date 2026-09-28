<?php

declare(strict_types=1);

namespace App\Workspace\Console;

use App\Workspace\Application\UpgradeNuxtProject;
use App\Workspace\Domain\Project;
use Tempest\Console\ConsoleCommand;
use Tempest\Console\ExitCode;
use Tempest\Console\HasConsole;

final readonly class UpgradeNuxtProjectCommand
{
    use HasConsole;
    use AskForWorkspacePath;

    public function __construct(
        private UpgradeNuxtProject $upgradeNuxtProject,
    ) {
    }

    #[ConsoleCommand(name: 'nuxt:upgrade')]
    public function __invoke(bool $cache = true): ExitCode
    {
        $path = $this->askForWorkspacePath($cache);

        if ($path === null) {
            $this->console->error('⚠️  Path is invalid');

            return ExitCode::INVALID;
        }

        $this->console->info("Scanning for Nuxt projects in: {$path}");

        $projects = $this->upgradeNuxtProject->findProjects($path);

        if ($projects === []) {
            $this->console->error("No Nuxt projects found in {$path}");

            return ExitCode::INVALID;
        }

        $selected = $this->select($projects);

        if ($selected === []) {
            $this->console->info('No projects selected');

            return ExitCode::SUCCESS;
        }

        $this->console->info('Upgrading ' . count($selected) . ' project(s)...');
        $this->console->writeln();

        foreach ($selected as $project) {
            $this->upgradeNuxtProject->upgrade($project);
            $this->console->writeln();
        }

        $this->console->info('✓ All projects upgraded successfully.');

        return ExitCode::SUCCESS;
    }

    /**
     * @param list<Project> $projects
     * @return list<Project>
     */
    private function select(array $projects): array
    {
        if (count($projects) === 1) {
            $this->console->info("Found 1 Nuxt project: {$projects[0]->name}");

            return $projects;
        }

        $this->console->info('Found ' . count($projects) . ' Nuxt projects');

        $labels = [];

        foreach ($projects as $index => $project) {
            $labels[$index] = "{$project->name} (Nuxt {$project->version})";
        }

        $chosen = $this->search(
            label: 'Select project(s) to upgrade',
            search: fn (string $search): array => array_filter(
                $labels,
                fn (string $label) => stripos($label, $search) !== false,
            ),
            multiple: true,
        );

        $chosen = is_array($chosen) ? $chosen : array_filter([$chosen]);

        return array_values(array_filter(
            $projects,
            fn (Project $project, int $index) => in_array($labels[$index], $chosen, strict: true),
            ARRAY_FILTER_USE_BOTH,
        ));
    }
}
