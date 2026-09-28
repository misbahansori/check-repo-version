<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

final readonly class Project
{
    public function __construct(
        public string $name,
        public string $path,
        public ProjectType $type,
        public ?string $version,
    ) {
    }

    public function is(ProjectType $type): bool
    {
        return $this->type === $type;
    }
}
