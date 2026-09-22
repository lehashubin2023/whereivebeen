<?php

namespace App\Actions\Journey;

use App\Models\Map;
use Illuminate\Support\Facades\DB;

class CalculateZoneStatistics
{
    private const TOP_ZONES = 8;

    private const ZONE_STEP_LIMIT = 600;

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{name: string, seconds: int, points: int}>
     */
    public function exec(array $ids): array
    {
        $seconds = [];
        $points = [];

        $previousSession = null;
        $previousTime = 0;
        $previousMap = null;

        DB::table('way_points')
            ->whereIn('game_session_id', $ids)
            ->whereNotNull('map_id')
            ->select(['game_session_id', 'map_id', 'time'])
            ->orderBy('game_session_id')
            ->orderBy('sequence')
            ->cursor()
            ->each(function (object $point) use (
                &$seconds,
                &$points,
                &$previousSession,
                &$previousTime,
                &$previousMap
            ) {
                $mapId = (int) $point->map_id;
                $time = (int) $point->time;

                $points[$mapId] = ($points[$mapId] ?? 0) + 1;

                if ($previousSession === $point->game_session_id && $previousMap === $mapId) {
                    $step = (int) round(($time - $previousTime) / 10);

                    if ($step > 0 && $step <= self::ZONE_STEP_LIMIT) {
                        $seconds[$mapId] = ($seconds[$mapId] ?? 0) + $step;
                    }
                }

                $previousSession = $point->game_session_id;
                $previousTime = $time;
                $previousMap = $mapId;
            });

        if ($points === []) {
            return [];
        }

        $names = Map::query()->whereIn('id', array_keys($points))->pluck('name', 'id')->all();

        $zones = [];

        foreach ($points as $mapId => $count) {
            $zones[] = [
                'name' => (string) ($names[$mapId] ?? ''),
                'seconds' => (int) ($seconds[$mapId] ?? 0),
                'points' => $count,
            ];
        }

        $zones = array_values(array_filter($zones, fn (array $zone) => $zone['name'] !== ''));

        usort($zones, fn (array $a, array $b) => $b['seconds'] <=> $a['seconds']);

        return array_slice($zones, 0, self::TOP_ZONES);
    }
}
