<?php

declare(strict_types=1);

namespace Poshtive\Router\Discovery;

final class BuildFingerprint
{
    /** @param list<DiscoveredRouteEntry> $entries */
    public static function generate(array $entries, string $packageVersion = ''): string
    {
        $ids = array_map(
            fn (DiscoveredRouteEntry $entry): string => $entry->id,
            $entries,
        );
        sort($ids, SORT_STRING);

        return hash('sha256', implode("\0", $ids)."\0".$packageVersion);
    }

    /** @param list<DiscoveredRouteEntry> $entries */
    public static function verify(string $fingerprint, array $entries, string $packageVersion = ''): bool
    {
        return hash_equals($fingerprint, self::generate($entries, $packageVersion));
    }
}
