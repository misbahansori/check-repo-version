<?php

declare(strict_types=1);

use App\Workspace\Domain\ComposerProjectDetector;
use App\Workspace\Domain\Manifest;
use App\Workspace\Domain\NpmProjectDetector;
use App\Workspace\Domain\ProjectType;

it('detects a Laravel project from composer.json', function () {
    $project = new ComposerProjectDetector()->detect(new Manifest('/work/shop', ['require' => ['laravel/framework' => '^12.0']]));

    expect($project->name)->toBe('shop')
        ->and($project->type)->toBe(ProjectType::Laravel)
        ->and($project->version)->toBe('^12.0');
});

it('detects Nuxt before Vue in package.json, including dev dependencies', function () {
    $project = new NpmProjectDetector()->detect(new Manifest('/work/site', [
        'dependencies' => ['vue' => '^3.5'],
        'devDependencies' => ['nuxt' => '^4.1'],
    ]));

    expect($project->type)->toBe(ProjectType::Nuxt)
        ->and($project->version)->toBe('^4.1');
});

it('reports unknown projects without a version', function () {
    $project = new NpmProjectDetector()->detect(new Manifest('/work/tool', []));

    expect($project->type)->toBe(ProjectType::Unknown)
        ->and($project->version)->toBeNull();
});
