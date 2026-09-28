<?php

declare(strict_types=1);

namespace App\Shared\Console;

use App\Shared\Application\Progress;
use Tempest\Console\Console;
use Tempest\Container\Autowire;

#[Autowire]
final readonly class ConsoleProgress implements Progress
{
    public function __construct(
        private Console $console,
    ) {
    }

    public function info(string $message): void
    {
        $this->console->info($message);
    }

    public function error(string $message): void
    {
        $this->console->error($message);
    }

    public function warning(string $message): void
    {
        $this->console->warning($message);
    }

    public function write(string $text): void
    {
        $this->console->write($text);
    }

    public function writeln(string $line = ''): void
    {
        $this->console->writeln($line);
    }
}
