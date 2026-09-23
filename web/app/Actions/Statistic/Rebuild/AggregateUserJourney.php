<?php

namespace App\Actions\Statistic\Rebuild;

use App\Enums\Statistic\StatisticBucketEnum;
use App\Models\GameSession;
use App\Models\SessionMapStat;
use App\Models\SessionStatisticEntry;
use Illuminate\Support\Carbon;

class AggregateUserJourney
{
    private const ACTIVITY_DAYS = 90;

    private const TOP_ZONES = 8;

    /**
     * @return array<string, mixed>
     */
    public function exec(int $userId): array
    {
        return [
            'activity' => $this->activity($userId),
            'zones' => $this->zones($userId),
            'levels' => $this->levels($userId),
            'deadliest' => $this->deadliest($userId),
        ];
    }

    /**
     * @return array<int, array{date: string, seconds: int, sessions: int}>
     */
    private function activity(int $userId): array
    {
        $byDay = GameSession::query()
            ->where('user_id', $userId)
            ->selectRaw('date(session_start_at) as day')
            ->selectRaw('sum(duration_seconds) as seconds')
            ->selectRaw('count(*) as sessions')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn (GameSession $row) => (string) $row->getAttribute('day'));

        if ($byDay->isEmpty()) {
            return [];
        }

        $days = $byDay->keys()->all();
        $last = Carbon::parse((string) end($days));
        $cursor = Carbon::parse((string) $days[0])->max($last->copy()->subDays(self::ACTIVITY_DAYS - 1));
        $activity = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $day = $cursor->toDateString();
            $row = $byDay->get($day);

            $activity[] = [
                'date' => $day,
                'seconds' => (int) $row?->getAttribute('seconds'),
                'sessions' => (int) $row?->getAttribute('sessions'),
            ];

            $cursor->addDay();
        }

        return $activity;
    }

    /**
     * @return array<int, array{map_id: int, seconds: int, points: int}>
     */
    private function zones(int $userId): array
    {
        return SessionMapStat::query()
            ->where('user_id', $userId)
            ->selectRaw('map_id, sum(seconds) as seconds, sum(points) as points')
            ->groupBy('map_id')
            ->orderByDesc('seconds')
            ->limit(self::TOP_ZONES)
            ->get()
            ->map(fn (SessionMapStat $row) => [
                'map_id' => (int) $row->getAttribute('map_id'),
                'seconds' => (int) $row->getAttribute('seconds'),
                'points' => (int) $row->getAttribute('points'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{level: int, date: string}>
     */
    private function levels(int $userId): array
    {
        return SessionStatisticEntry::query()
            ->join('game_sessions', 'game_sessions.id', '=', 'session_statistic_entries.game_session_id')
            ->where('session_statistic_entries.user_id', $userId)
            ->where('session_statistic_entries.bucket', StatisticBucketEnum::LEVELUP_LEVEL->value)
            ->selectRaw('session_statistic_entries.entry_key as player_level')
            ->selectRaw('min(game_sessions.session_start_at) as reached_at')
            ->groupBy('session_statistic_entries.entry_key')
            ->orderByRaw('cast(session_statistic_entries.entry_key as unsigned)')
            ->get()
            ->map(fn (SessionStatisticEntry $row) => [
                'level' => (int) $row->getAttribute('player_level'),
                'date' => Carbon::parse((string) $row->getAttribute('reached_at'))->toDateString(),
            ])
            ->all();
    }

    /**
     * @return array{map_id: int, deaths: int}|null
     */
    private function deadliest(int $userId): ?array
    {
        $row = SessionMapStat::query()
            ->where('user_id', $userId)
            ->selectRaw('map_id, sum(deaths) as deaths')
            ->groupBy('map_id')
            ->havingRaw('sum(deaths) > 0')
            ->orderByDesc('deaths')
            ->first();

        return $row === null
            ? null
            : ['map_id' => (int) $row->getAttribute('map_id'), 'deaths' => (int) $row->getAttribute('deaths')];
    }
}
