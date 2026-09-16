<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\WayPoint;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection as SupportCollection;

class BuildSessionZones
{
    private const GAP_SECONDS = 60;

    private bool $mounted = false;

    private bool $flying = false;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exec(GameSession $gameSession): array
    {
        $points = WayPoint::query()
            ->where('way_points.game_session_id', $gameSession->id)
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

        $points->each(fn (WayPoint $point) => $point->setAttribute('state', $this->defineState($point)));

        $maps = $this->renderableMaps($points);

        return $this->buildZones(
            $this->splitIntoVisits($this->keepRenderable($points, $maps)),
            $maps,
            $gameSession
        );
    }

    /**
     * @param  Collection<int, WayPoint>  $points
     * @param  SupportCollection<int, Map>  $maps
     * @return Collection<int, WayPoint>
     */
    private function keepRenderable(Collection $points, SupportCollection $maps): Collection
    {
        $visible = [];
        $leftWorld = false;

        foreach ($points as $point) {
            $isGap = $point->getAttribute('event_type_id') === EventTypeEnum::GAP->value;

            if (! $maps->has((int) $point->getAttribute('map_id'))) {
                $leftWorld = $leftWorld || $isGap;

                continue;
            }

            $point->setAttribute('left_world', $isGap || $leftWorld);
            $leftWorld = false;

            $visible[] = $point;
        }

        return new Collection($visible);
    }

    /**
     * @param  Collection<int, WayPoint>  $points
     * @return SupportCollection<int, Map>
     */
    private function renderableMaps(Collection $points): SupportCollection
    {
        return Map::query()
            ->whereIn('id', $points->pluck('map_id')->filter()->unique()->all())
            ->get()
            ->filter(fn (Map $map) => file_exists(
                public_path(ltrim((string) $map->getAttribute('image_path'), '/')),
            ))
            ->keyBy('id');
    }

    /**
     * @param  Collection<int, WayPoint>  $points
     * @return array<int, array{map_id: int, last_time: int, points: array<int, WayPoint>}>
     */
    private function splitIntoVisits(Collection $points): array
    {
        /** @var list<array{map_id: int, last_time: int, points: list<WayPoint>}> $visits */
        $visits = [];
        /** @var array<int, int> $openByMap */
        $openByMap = [];
        $previousMapId = null;
        $current = 0;

        foreach ($points as $point) {
            $mapId = (int) $point->getAttribute('map_id');
            $time = (int) $point->getAttribute('time');
            $left = (bool) $point->getAttribute('left_world');

            if ($left || $previousMapId !== $mapId) {
                $open = $left ? null : ($openByMap[$mapId] ?? null);

                if ($open === null || $time - $visits[$open]['last_time'] > self::GAP_SECONDS * 10) {
                    $visits[] = ['map_id' => $mapId, 'last_time' => $time, 'points' => []];
                    $open = array_key_last($visits);
                    $openByMap[$mapId] = $open;
                }

                $current = $open;
            }

            $visit = $visits[$current];

            $point->setAttribute('gap', $visit['points'] === []);
            $point->setAttribute('event_slug', $this->defineEvent($point));

            $visit['points'][] = $point;
            $visit['last_time'] = $time;

            $visits[$current] = $visit;
            $previousMapId = $mapId;
        }

        return $visits;
    }

    /**
     * @param  array<int, array{map_id: int, last_time: int, points: array<int, WayPoint>}>  $visits
     * @param  SupportCollection<int, Map>  $maps
     * @return array<int, array<string, mixed>>
     */
    private function buildZones(array $visits, SupportCollection $maps, GameSession $gameSession): array
    {
        $zones = [];

        foreach ($visits as $visit) {
            $map = $maps->get($visit['map_id']);

            if ($map === null) {
                continue;
            }

            $imagePath = (string) $map->getAttribute('image_path');
            $started = (int) $visit['points'][0]->getAttribute('time');

            $zones[] = [
                'key' => $visit['map_id'].'-'.(int) $visit['points'][0]->getAttribute('sequence'),
                'id' => $visit['map_id'],
                'name' => $map->name,
                'image_path' => $imagePath,
                'points_count' => count($visit['points']),
                'time' => $gameSession->session_start_at
                    ->copy()
                    ->addMilliseconds($started * 100)
                    ->toIso8601String(),
                'duration' => (int) round(($visit['last_time'] - $started) / 10),
                'points' => $this->serializePoints($visit['points']),
            ];
        }

        return $zones;
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
                'time' => (int) $point->getAttribute('time'),
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
