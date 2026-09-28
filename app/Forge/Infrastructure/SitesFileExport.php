<?php

declare(strict_types=1);

namespace App\Forge\Infrastructure;

use App\Forge\Application\ServerSites;

final readonly class SitesFileExport
{
    /**
     * Write each server's sites as a block: server name, header row, one row per site, blank line.
     * `.tsv` and `.txt` files are tab separated, anything else is CSV.
     *
     * @param list<string> $headers
     * @param list<ServerSites> $servers
     */
    public static function write(string $path, array $headers, array $servers): bool
    {
        $handle = @fopen($path, 'w');

        if ($handle === false) {
            return false;
        }

        $useTabs = in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['tsv', 'txt'], strict: true);

        $write = function (array $fields) use ($handle, $useTabs): void {
            if ($useTabs) {
                fwrite($handle, implode("\t", $fields) . PHP_EOL);

                return;
            }

            fputcsv($handle, $fields, ',', '"', '\\');
        };

        foreach ($servers as $server) {
            $write([$server->target->server->name]);
            $write($headers);

            foreach ($server->sites as $site) {
                $write($site->toArray());
            }

            $write([]);
        }

        fclose($handle);

        return true;
    }
}
