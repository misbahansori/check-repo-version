<?php

declare(strict_types=1);

namespace App\Workspace\Console;

use Tempest\Cache\Cache;

use function Tempest\Container\get;

trait AskForWorkspacePath
{
    /**
     * Ask for the workspace folder, reusing the last answer unless `$useCache` is false.
     */
    private function askForWorkspacePath(bool $useCache = true): ?string
    {
        $cache = get(Cache::class);

        if ($useCache) {
            $path = $cache->resolve(
                key: 'workspace-path',
                callback: fn () => $this->ask('Enter the path to the repository:'),
            );
        } else {
            $path = $this->ask('Enter the path to the repository:');

            $cache->put(key: 'workspace-path', value: $path);
        }

        $path = str_replace('~', $_SERVER['HOME'] ?? $_SERVER['USERPROFILE'], $path);

        return is_dir($path) ? $path : null;
    }
}
