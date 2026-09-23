<?php

namespace App\Actions\Statistic\Rebuild;

use App\Models\GameSession;
use App\Models\SessionEventCount;
use App\Models\SessionMapStat;

class AggregateUserOverview
{
    /**
     * @return array<string, int>
     */
    public function exec(int $userId): array
    {
        $sessions = GameSession::query()
            ->where('user_id', $userId)
            ->selectRaw('count(*) as sessions_count')
            ->selectRaw('coalesce(sum(points_count), 0) as points_count')
            ->selectRaw('coalesce(sum(duration_seconds), 0) as seconds_played')
            ->first();

        return [
            'sessions_count' => (int) $sessions?->getAttribute('sessions_count'),
            'points_count' => (int) $sessions?->getAttribute('points_count'),
            'seconds_played' => (int) $sessions?->getAttribute('seconds_played'),
            'events_count' => (int) SessionEventCount::query()->where('user_id', $userId)->sum('total'),
            'zones_count' => SessionMapStat::query()->where('user_id', $userId)->distinct()->count('map_id'),
        ];
    }
}
