<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\WayPoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;

class BuildSessionZones
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function exec(GameSession $gameSession): array
    {
        $points = WayPoint::query()
            ->where('way_points.game_session_id', $gameSession->id)
            ->whereNotNull('map_id')
            ->leftJoin('events', function (JoinClause $join) {
                $join->on('events.game_session_id', '=', 'way_points.game_session_id')
                    ->on('events.sequence', '=', 'way_points.sequence')
                    ->whereIn('events.event_type_id', [
                        EventTypeEnum::MOUNT->value,
                        EventTypeEnum::TAXI->value,
                        EventTypeEnum::GAP->value,
                    ]);
            })
            ->orderBy('way_points.sequence')
            ->withCasts(['payload' => 'json'])
            ->get([
                'way_points.sequence',
                'way_points.map_id',
                'way_points.x',
                'way_points.y',
                'events.event_type_id',
                'events.payload',
            ]);

        $points->transform(function ($point) {
            $point->state = $this->defineState($point);
            $point->gap = $this->defineGap($point);
            return $point;
        });

        return $this->buildZones($points);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>
     */
    private function buildZones(Collection $points): array
    {
        $groups = $points->groupBy('map_id');
        $maps = Map::query()
            ->whereIn('id', $groups->keys())
            ->get()
            ->keyBy('id');
        $zones = [];

        foreach ($groups as $mapId => $zonePoints) {
            $map = $maps->get($mapId);

            if ($map === null) {
                continue;
            }

            $imagePath = (string) $map->getAttribute('image_path');

            if (! file_exists(public_path(ltrim($imagePath, '/')))) {
                continue;
            }

            $zones[] = [
                'id' => $mapId,
                'name' => $map->name,
                'image_path' => $imagePath,
                'points_count' => count($zonePoints),
                'points' => $zonePoints,
            ];
        }

        return collect($zones)
            ->sortByDesc('points_count')
            ->values()
            ->all();
    }

    private function defineState(WayPoint &$point): string
    {
        static $mounted = false;
        static $flying = false;

        $eventTypeId = $point->getAttribute('event_type_id');

        if ($eventTypeId === EventTypeEnum::MOUNT->value || $eventTypeId === EventTypeEnum::TAXI->value) {
            $payload = (array) $point->getAttribute('payload');

            if ($eventTypeId === EventTypeEnum::MOUNT->value) {
                $mounted = (bool) ($payload['mounted'] ?? false);

                if ($mounted) {
                    $flying = false;
                }
            } else {
                $flying = (bool) ($payload['on_taxi'] ?? false);

                if ($flying) {
                    $mounted = false;
                }
            }
        }

        return $mounted ? 'mounted' : ($flying ? 'flying' : 'ground') ;
    }

    private function defineGap(WayPoint &$point): bool
    {
        static $prevMapId = null;

        $isGap = $point['event_type_id'] === EventTypeEnum::GAP->value || $prevMapId !== $point['map_id'];

        $prevMapId = $point['map_id'];

        return $isGap;
    }
}
