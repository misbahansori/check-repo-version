<?php

declare(strict_types=1);

namespace App\Forge\Domain;

final readonly class Site
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $repository = null,
        public ?string $repositoryBranch = null,
        public ?string $appType = null,
        public ?string $deploymentScript = null,
        public ?string $deploymentUrl = null,
    ) {
    }

    /**
     * The repository as `owner/repo:branch`, or `-` when the site has none.
     */
    public function repositoryReference(): string
    {
        if ($this->repository === null || $this->repository === '') {
            return '-';
        }

        // Forge returns the full clone URL — reduce it to `owner/repo`.
        $repository = preg_match('/[:\/]([^\/]+\/[^\/]+?)(?:\.git)?$/', $this->repository, $matches) === 1
            ? $matches[1]
            : $this->repository;

        return $this->repositoryBranch !== null && $this->repositoryBranch !== ''
            ? "{$repository}:{$this->repositoryBranch}"
            : $repository;
    }

    /**
     * Forge reports most non-PHP sites as "Custom", so fall back to sniffing
     * the deployment script for the toolchain the site actually uses.
     */
    public function projectType(): string
    {
        $appType = $this->appType ?? '';

        if ($appType !== '' && strtolower($appType) !== 'custom') {
            return $appType;
        }

        $script = strtolower($this->deploymentScript ?? '');

        return match (true) {
            str_contains($script, 'artisan') => 'Laravel',
            str_contains($script, 'nuxt') || str_contains($script, '.output/server') => 'Nuxt',
            str_contains($script, 'next build') => 'Next',
            str_contains($script, 'go build') || str_contains($script, 'go mod') => 'GO',
            str_contains($script, 'npm') || str_contains($script, 'pnpm') || str_contains($script, 'yarn') || str_contains($script, 'node ') => 'Node',
            default => $appType !== '' ? $appType : '-',
        };
    }

    public function hasDeploymentUrl(): bool
    {
        return $this->deploymentUrl !== null && $this->deploymentUrl !== '';
    }
}
