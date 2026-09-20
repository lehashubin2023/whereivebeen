<?php

namespace App\Support\GameSession;

use App\Models\Map;

final class MapId
{
    public static function sanitize(mixed $mapId): ?int
    {
        if ($mapId === null || $mapId === '' || ! is_numeric($mapId)) {
            return null;
        }

        $id = (int) $mapId;

        if ($id <= 0 || $id > Map::MAX_ID) {
            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     * @return list<int>
     */
    public static function distinctFromPoints(array $points): array
    {
        /** @var array<int, true> $seen */
        $seen = [];

        foreach ($points as $point) {
            $id = self::sanitize($point['mapId'] ?? null);

            if ($id !== null) {
                $seen[$id] = true;
            }
        }

        return array_keys($seen);
    }
}
