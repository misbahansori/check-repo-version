<?php

declare(strict_types=1);

namespace App\Forge\Domain;

/**
 * Finds the port a site listens on from its configuration files.
 */
final class SitePort
{
    private const array ENVIRONMENT_KEYS = ['PORT', 'APP_PORT', 'NUXT_PORT', 'NITRO_PORT', 'SERVER_PORT', 'HTTP_PORT'];

    public static function fromEnvironment(string $environment): ?string
    {
        foreach (self::ENVIRONMENT_KEYS as $key) {
            if (preg_match('/^\s*' . $key . '\s*=\s*"?(\d{2,5})"?\s*$/m', $environment, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    public static function fromNginx(string $nginx): ?string
    {
        return preg_match('/proxy_pass\s+https?:\/\/[^:\s]+:(\d{2,5})/', $nginx, $matches) === 1
            ? $matches[1]
            : null;
    }
}
