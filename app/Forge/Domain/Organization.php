<?php

declare(strict_types=1);

namespace App\Forge\Domain;

final readonly class Organization
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
    ) {
    }
}
