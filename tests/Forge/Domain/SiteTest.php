<?php

declare(strict_types=1);

use App\Forge\Domain\Site;
use App\Forge\Domain\SitePort;

it('reduces the repository URL to owner/repo:branch', function (string $repository, ?string $branch, string $expected) {
    expect(new Site(1, 'example.com', $repository, $branch)->repositoryReference())->toBe($expected);
})->with([
    ['git@github.com:acme/shop.git', 'main', 'acme/shop:main'],
    ['https://github.com/acme/shop', null, 'acme/shop'],
    ['', 'main', '-'],
]);

it('sniffs the project type from the deployment script when Forge says Custom', function (?string $appType, ?string $script, string $expected) {
    expect(new Site(1, 'example.com', appType: $appType, deploymentScript: $script)->projectType())->toBe($expected);
})->with([
    ['php', null, 'php'],
    ['Custom', 'php artisan migrate --force', 'Laravel'],
    ['custom', 'node .output/server/index.mjs', 'Nuxt'],
    ['Custom', 'go build ./...', 'GO'],
    [null, 'pnpm install', 'Node'],
    ['Custom', 'echo hi', 'Custom'],
    [null, null, '-'],
]);

it('reads the port from the environment file', function () {
    expect(SitePort::fromEnvironment("APP_NAME=shop\nNITRO_PORT=\"3001\"\n"))->toBe('3001')
        ->and(SitePort::fromEnvironment('APP_NAME=shop'))->toBeNull();
});

it('reads the port from the nginx proxy_pass', function () {
    expect(SitePort::fromNginx('proxy_pass http://127.0.0.1:3005;'))->toBe('3005')
        ->and(SitePort::fromNginx('root /home/forge/site/public;'))->toBeNull();
});
