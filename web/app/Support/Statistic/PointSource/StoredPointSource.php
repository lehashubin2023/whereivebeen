<?php

namespace App\Support\Statistic\PointSource;

use App\Enums\GameSession\EventTypeEnum;
use App\Support\Statistic\StatisticPoint;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

class StoredPointSource implements PointSourceContract
{
    public function __construct(private readonly int $gameSessionId) {}

    public function points(): iterable
    {
        $rows = DB::table('way_points')
            ->leftJoin('events', function (JoinClause $join) {
                $join->on('events.game_session_id', '=', 'way_points.game_session_id')
                    ->on('events.sequence', '=', 'way_points.sequence');
            })
            ->where('way_points.game_session_id', $this->gameSessionId)
            ->orderBy('way_points.sequence')
            ->select([
                'way_points.map_id',
                'way_points.time',
                'events.event_type_id',
                'events.payload',
            ])
            ->cursor();

        foreach ($rows as $row) {
            $event = $row->event_type_id === null
                ? null
                : EventTypeEnum::tryFrom((int) $row->event_type_id);

            yield new StatisticPoint(
                time: (int) $row->time,
                mapId: $row->map_id === null ? null : (int) $row->map_id,
                event: $event,
                payload: $event === null ? [] : self::payload($row->payload),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function payload(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
