<?php

namespace App\Actions\Journey;

use App\Actions\GameSession\MeasureSessionTime;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CalculateUserActivity
{
    private const ACTIVITY_DAYS = 90;

    public function __construct(private readonly MeasureSessionTime $measureTime) {}

    /**
     * @param  Collection<int, GameSession>  $sessions
     * @param  array<int, int>  $ids
     * @return array<int, array{date: string, seconds: int, sessions: int}>
     */
    public function exec($sessions, array $ids): array
    {
        $durations = $this->measureTime->exec($ids);
        $byDay = [];

        foreach ($sessions as $session) {
            $day = $session->session_start_at->toDateString();

            $byDay[$day]['seconds'] = ($byDay[$day]['seconds'] ?? 0) + ($durations[$session->id] ?? 0);
            $byDay[$day]['sessions'] = ($byDay[$day]['sessions'] ?? 0) + 1;
        }

        if ($byDay === []) {
            return [];
        }

        $days = array_keys($byDay);
        sort($days);

        $from = Carbon::parse(end($days))->subDays(self::ACTIVITY_DAYS - 1);
        $start = Carbon::parse($days[0])->max($from);
        $cursor = $start->copy();
        $last = Carbon::parse(end($days));
        $result = [];

        while ($cursor->lessThanOrEqualTo($last)) {
            $day = $cursor->toDateString();

            $result[] = [
                'date' => $day,
                'seconds' => (int) ($byDay[$day]['seconds'] ?? 0),
                'sessions' => (int) ($byDay[$day]['sessions'] ?? 0),
            ];

            $cursor->addDay();
        }

        return $result;
    }
}
