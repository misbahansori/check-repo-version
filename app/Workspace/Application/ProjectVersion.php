<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use App\Workspace\Domain\Project;

final readonly class ProjectVersion
{
    public function __construct(
        public Project $project,
        public ?string $branch,
    ) {
    }
}
