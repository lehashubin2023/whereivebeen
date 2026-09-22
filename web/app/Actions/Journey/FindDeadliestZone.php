<?php

namespace App\Actions\Journey;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Map;
use Illuminate\Support\Facades\DB;

class FindDeadliestZone
{
    /**
     * @param  array<int, int>  $ids
     * @return array{name: string, deaths: int}|null
     */
    public function exec(array $ids): ?array
    {
        $deaths = DB::table('events')
            ->join('way_points', function ($join) {
                $join->on('way_points.game_session_id', '=', 'events.game_session_id')
                    ->on('way_points.sequence', '=', 'events.sequence');
            })
            ->whereIn('events.game_session_id', $ids)
            ->where('events.event_type_id', EventTypeEnum::DEATH->value)
            ->whereNotNull('way_points.map_id')
            ->selectRaw('way_points.map_id, count(*) as deaths')
            ->groupBy('way_points.map_id')
            ->orderByDesc('deaths')
            ->first();

        if ($deaths === null) {
            return null;
        }

        $name = Map::query()->where('id', $deaths->map_id)->value('name');

        return $name === null ? null : ['name' => (string) $name, 'deaths' => (int) $deaths->deaths];
    }
}
