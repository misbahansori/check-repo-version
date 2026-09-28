<?php

declare(strict_types=1);

namespace App\Workspace\Infrastructure;

use App\Workspace\Application\CommandRunner;
use Closure;
use Tempest\Container\Autowire;

#[Autowire]
final readonly class ShellCommandRunner implements CommandRunner
{
    public function run(string $command, string $workingDirectory, Closure $onOutput): bool
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $workingDirectory);

        if (! is_resource($process)) {
            return false;
        }

        while (($line = fgets($pipes[1])) !== false) {
            if (trim($line) !== '') {
                $onOutput(trim($line));
            }
        }

        fclose($pipes[1]);
        proc_close($process);

        return true;
    }
}
