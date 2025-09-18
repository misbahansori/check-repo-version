<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/public',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withSets([__DIR__ . '/vendor/tempest/framework/packages/upgrade/src/tempest2.php']);
