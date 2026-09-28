<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Tempest\Upgrade\Set\TempestLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/public',
    ])
    // uncomment to reach your current PHP version
    // ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withSets([TempestLevelSetList::UP_TO_TEMPEST_314]);
