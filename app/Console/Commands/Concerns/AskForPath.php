<?php

namespace App\Console\Commands\Concerns;

use Tempest\Cache\Cache;
use function Tempest\get;

use Tempest\Console\ExitCode;

trait AskForPath
{
    private function askForPath(bool $shouldUseCache = true): ?string
    {
        $cache = get(Cache::class);

        if ($shouldUseCache) {
            $path = $cache->resolve(
                key: 'workspace-path',
                callback: fn() => $this->ask('Enter the path to the repository:')
            );
        } else {
            $path = $this->ask('Enter the path to the repository:');

            $cache->put(
                key: 'workspace-path',
                value: $path
            );
        }

        $path = $this->normalizePath($path);

        if (!is_dir($path)) return null;

        return $path;
    }

    private function normalizePath(string $path): string
    {
        return str_replace('~', $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'], $path);
    }
}
