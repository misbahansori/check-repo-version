<?php

declare(strict_types=1);

namespace App\Forge\Infrastructure;

use App\Forge\Application\EnvironmentDump;
use App\Forge\Domain\Organization;
use App\Forge\Domain\Server;
use App\Forge\Domain\Site;

final class TextFileEnvironmentDump implements EnvironmentDump
{
    /**
     * @param resource $handle
     */
    private function __construct(
        private $handle,
    ) {
    }

    public static function open(string $path): ?self
    {
        $handle = @fopen($path, 'w');

        if ($handle === false) {
            return null;
        }

        $dump = new self($handle);
        $dump->line('Forge .env dump — generated at ' . date('c'));
        $dump->line('');

        return $dump;
    }

    public function organization(Organization $organization): void
    {
        $this->line(str_repeat('=', 80));
        $this->line("Organization: {$organization->name} (slug: {$organization->slug}, id: {$organization->id})");
        $this->line(str_repeat('=', 80));
        $this->line('');
    }

    public function server(Server $server): void
    {
        $this->line(str_repeat('-', 80));
        $this->line("Server: {$server->name} (id: {$server->id})");
        $this->line(str_repeat('-', 80));
        $this->line('');
    }

    public function site(Site $site, string $environment): void
    {
        $this->line("--- Site: {$site->name} (id: {$site->id}) ---");
        $this->line($environment);
        $this->line('');
    }

    public function siteFailed(Site $site, string $message): void
    {
        $this->line("--- Site: {$site->name} (id: {$site->id}) ---");
        $this->line("ERROR: {$message}");
        $this->line('');
    }

    public function close(): void
    {
        fclose($this->handle);
    }

    private function line(string $line): void
    {
        fwrite($this->handle, $line . PHP_EOL);
    }
}
