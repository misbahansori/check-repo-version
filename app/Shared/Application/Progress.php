<?php

declare(strict_types=1);

namespace App\Shared\Application;

interface Progress
{
    public function info(string $message): void;

    public function error(string $message): void;

    public function warning(string $message): void;

    public function write(string $text): void;

    public function writeln(string $line = ''): void;
}
