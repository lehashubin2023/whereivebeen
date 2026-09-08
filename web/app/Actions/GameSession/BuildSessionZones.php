<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\WayPoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;

class BuildSessionZones
{
    /**
     * Отлучка на другую карту, после которой линия рвётся. Короткие отлучки — это
     * дребезг границы зон (`GetBestMapForUnit` на границе прыгает туда-обратно
     * за секунды), и разрывать маршрут из-за них нельзя.
     */
    private const GAP_SECONDS = 60;

    private bool $mounted = false;

    private bool $flying = false;

    /** @var array<int, int> последнее время (децисекунды) по карте */
    private array $lastTimeByMap = [];

    private ?int $previousMapId = null;

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
                    ->on('events.sequence', '=', 'way_points.sequence');
            })
            ->orderBy('way_points.sequence')
            ->withCasts(['payload' => 'json'])
            ->get([
                'way_points.sequence',
                'way_points.map_id',
                'way_points.time',
                'way_points.x',
                'way_points.y',
                'events.event_type_id',
                'events.payload',
            ]);

        $this->mounted = false;
        $this->flying = false;
        $this->lastTimeByMap = [];
        $this->previousMapId = null;

        $points->transform(function ($point) {
            $point->state = $this->defineState($point);
            $point->gap = $this->defineGap($point);
            $point->setAttribute('event_slug', $this->defineEvent($point));

            return $point;
        });

        return $this->buildZones($points, $gameSession);
    }

    /**
     * @param  Collection<int, WayPoint>  $points
     * @return array<int, array<string, mixed>>
     */
    private function buildZones(Collection $points, GameSession $gameSession): array
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
                'time' => $gameSession->session_start_at
                    ->copy()
                    ->addMilliseconds((int) $zonePoints->min('time') * 100)
                    ->toIso8601String(),
                'first_sequence' => (int) $zonePoints->min('sequence'),
                'points' => $this->serializePoints($zonePoints),
            ];
        }

        return collect($zones)
            ->sortBy(fn (array $zone) => $zone['first_sequence'])
            ->map(fn (array $zone) => Arr::except($zone, 'first_sequence'))
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, WayPoint>  $points
     * @return array<int, array<string, mixed>>
     */
    private function serializePoints(iterable $points): array
    {
        $result = [];

        foreach ($points as $point) {
            $result[] = [
                'sequence' => (int) $point->getAttribute('sequence'),
                'x' => $point->getAttribute('x'),
                'y' => $point->getAttribute('y'),
                'state' => $point->getAttribute('state'),
                'gap' => $point->getAttribute('gap'),
                'event' => $point->getAttribute('event_slug'),
            ];
        }

        return $result;
    }

    private function defineState(WayPoint &$point): string
    {
        $eventTypeId = $point->getAttribute('event_type_id');

        if ($eventTypeId === EventTypeEnum::MOUNT->value || $eventTypeId === EventTypeEnum::TAXI->value) {
            $payload = (array) $point->getAttribute('payload');

            if ($eventTypeId === EventTypeEnum::MOUNT->value) {
                $this->mounted = (bool) ($payload['mounted'] ?? false);

                if ($this->mounted) {
                    $this->flying = false;
                }
            } else {
                $this->flying = (bool) ($payload['on_taxi'] ?? false);

                if ($this->flying) {
                    $this->mounted = false;
                }
            }
        }

        return $this->mounted ? 'mounted' : ($this->flying ? 'flying' : 'ground');
    }

    private function defineGap(WayPoint &$point): bool
    {
        $mapId = (int) $point['map_id'];
        $time = (int) $point['time'];

        $previous = $this->lastTimeByMap[$mapId] ?? null;
        $previousMapId = $this->previousMapId;

        $this->lastTimeByMap[$mapId] = $time;
        $this->previousMapId = $mapId;

        if ($point['event_type_id'] === EventTypeEnum::GAP->value) {
            return true;
        }

        if ($previous === null) {
            return true;
        }

        if ($previousMapId === $mapId) {
            return false;
        }

        return ($time - $previous) > self::GAP_SECONDS * 10;
    }

    private function defineEvent(WayPoint &$point): ?string
    {
        $eventTypeId = $point->getAttribute('event_type_id');

        if ($eventTypeId === null) {
            return null;
        }

        $type = EventTypeEnum::tryFrom((int) $eventTypeId);

        return $type !== null && $type->hasMarker() ? $type->slug() : null;
    }
}
