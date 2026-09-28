<?php

declare(strict_types=1);

namespace App\Workspace\Application;

enum PullStatus: string
{
    case Success = 'success';
    case Skipped = 'skipped';
    case Error = 'error';
}
