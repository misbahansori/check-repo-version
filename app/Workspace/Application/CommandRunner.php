<?php

declare(strict_types=1);

namespace App\Workspace\Application;

use Closure;

interface CommandRunner
{
    /**
     * Run a shell command, streaming each non-empty output line to `$onOutput`.
     *
     * @param Closure(string): void $onOutput
     * @return bool False when the command could not be started.
     */
    public function run(string $command, string $workingDirectory, Closure $onOutput): bool;
}
