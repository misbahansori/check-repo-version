<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

/**
 * A decoded dependency manifest such as composer.json or package.json.
 */
final readonly class Manifest
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $projectPath,
        public array $data,
    ) {
    }

    public function projectName(): string
    {
        return basename($this->projectPath);
    }

    /**
     * @return array<string, string>
     */
    public function section(string $key): array
    {
        $section = $this->data[$key] ?? [];

        return is_array($section) ? $section : [];
    }
}
