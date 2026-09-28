<?php

declare(strict_types=1);

namespace App\Workspace\Domain;

enum ProjectType: string
{
    case Laravel = 'laravel';
    case Symfony = 'symfony';
    case Nuxt = 'nuxt';
    case Next = 'nextjs';
    case Vue = 'vue';
    case Unknown = 'unknown';
}
