<?php

declare(strict_types=1);

namespace App\Forge\Infrastructure;

use App\Forge\Domain\ForgeGateway;
use App\Forge\Domain\Organization;
use App\Forge\Domain\Server;
use App\Forge\Domain\Site;
use Laravel\Forge\CursorPaginator;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Organization as ForgeOrganization;
use Laravel\Forge\Resources\Server as ForgeServer;
use Laravel\Forge\Resources\Site as ForgeSite;
use RuntimeException;
use Tempest\Container\Autowire;

/**
 * Forge API client that throttles every request to stay under Forge's rate limit.
 */
#[Autowire]
final class ForgeApi implements ForgeGateway
{
    private const int DELAY_MICROSECONDS = 250_000;

    private ?Forge $forge = null;

    public function isConfigured(): bool
    {
        return $this->token() !== null;
    }

    public function organizations(): iterable
    {
        foreach ($this->paginate(fn (Forge $forge) => $forge->organizations()) as $organization) {
            /** @var ForgeOrganization $organization */
            yield new Organization((string) $organization->id, (string) $organization->slug, (string) $organization->name);
        }
    }

    public function servers(Organization $organization): iterable
    {
        foreach ($this->paginate(fn (Forge $forge) => $forge->servers($organization->slug)) as $server) {
            /** @var ForgeServer $server */
            yield new Server((int) $server->id, (string) $server->name);
        }
    }

    public function sites(Organization $organization, Server $server): iterable
    {
        foreach ($this->paginate(fn (Forge $forge) => $forge->serverSites($organization->slug, $server->id)) as $site) {
            yield $this->toSite($site);
        }
    }

    public function environment(Organization $organization, Server $server, Site $site): string
    {
        return $this->call(fn (Forge $forge) => $forge->siteEnvironment($organization->slug, $server->id, $site->id));
    }

    public function nginxConfiguration(Organization $organization, Server $server, Site $site): string
    {
        return $this->call(fn (Forge $forge) => $forge->siteNginx($organization->slug, $server->id, $site->id));
    }

    public function deploymentTriggerUrl(Organization $organization, Server $server, Site $site): string
    {
        return $this->call(fn (Forge $forge) => $forge->deploymentTriggerUrl($organization->slug, $server->id, $site->id));
    }

    private function toSite(ForgeSite $site): Site
    {
        $repository = $site->repository;
        $branch = $site->attributes['repository_branch'] ?? null;

        if (is_array($repository)) {
            $branch = $repository['branch'] ?? $branch;
            $repository = $repository['name'] ?? $repository['url'] ?? null;
        }

        return new Site(
            id: (int) $site->id,
            name: (string) $site->name,
            repository: is_string($repository) ? $repository : null,
            repositoryBranch: is_string($branch) ? $branch : null,
            appType: $site->appType,
            deploymentScript: $site->deploymentScript,
            deploymentUrl: $site->deploymentUrl,
        );
    }

    /**
     * @param callable(Forge): CursorPaginator $request
     * @return iterable<mixed>
     */
    private function paginate(callable $request): iterable
    {
        $page = $this->call($request);

        while ($page !== null) {
            foreach ($page->items() as $item) {
                yield $item;
            }

            if (! $page->hasMorePages()) {
                break;
            }

            $page = $this->call(fn () => $page->nextPage());
        }
    }

    /**
     * @template T
     * @param callable(Forge): T $request
     * @return T
     */
    private function call(callable $request): mixed
    {
        usleep(self::DELAY_MICROSECONDS);

        return $request($this->forge());
    }

    private function forge(): Forge
    {
        return $this->forge ??= new Forge($this->token() ?? throw new RuntimeException('FORGE_API_TOKEN environment variable is not set.'));
    }

    private function token(): ?string
    {
        return getenv('FORGE_API_TOKEN') ?: ($_ENV['FORGE_API_TOKEN'] ?? null);
    }
}
