<?php

namespace App\Actions\Statistic;

use App\Actions\GameSession\MeasureSessionTime;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BuildUserJourney
{
    private const ACTIVITY_DAYS = 90;

    private const TOP_ZONES = 8;

    private const ZONE_STEP_LIMIT = 600;

    public function __construct(private readonly MeasureSessionTime $measureTime) {}

    /**
     * @return array<string, mixed>
     */
    public function exec(User $user): array
    {
        $sessions = GameSession::query()
            ->where('user_id', $user->id)
            ->get(['id', 'session_start_at']);

        if ($sessions->isEmpty()) {
            return ['activity' => [], 'zones' => [], 'levels' => [], 'deadliest' => null];
        }

        $ids = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [
            'activity' => $this->activity($sessions, $ids),
            'zones' => $this->zones($ids),
            'levels' => $this->levels($sessions, $ids),
            'deadliest' => $this->deadliest($ids),
        ];
    }

    /**
     * @param  Collection<int, GameSession>  $sessions
     * @param  array<int, int>  $ids
     * @return array<int, array{date: string, seconds: int, sessions: int}>
     */
    private function activity($sessions, array $ids): array
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

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{name: string, seconds: int, points: int}>
     */
    private function zones(array $ids): array
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

    /**
     * @param  Collection<int, GameSession>  $sessions
     * @param  array<int, int>  $ids
     * @return array<int, array{level: int, date: string}>
     */
    private function levels($sessions, array $ids): array
    {
        $startedAt = $sessions->pluck('session_start_at', 'id');
        $levels = [];

        Event::query()
            ->whereIn('game_session_id', $ids)
            ->where('event_type_id', EventTypeEnum::LEVELUP->value)
            ->select(['game_session_id', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$levels, $startedAt) {
                $payload = $event->getAttribute('payload');

                if (! is_array($payload) || ! isset($payload['level'])) {
                    return;
                }

                $start = $startedAt[$event->getAttribute('game_session_id')] ?? null;

                if ($start === null) {
                    return;
                }

                $levels[(int) $payload['level']] = $start->toDateString();
            });

        ksort($levels);

        $result = [];

        foreach ($levels as $level => $date) {
            $result[] = ['level' => $level, 'date' => $date];
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{name: string, deaths: int}|null
     */
    private function deadliest(array $ids): ?array
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
