<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\WayPoint;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Список сессий, из которого понятно, что это была за игра: откуда куда сходил,
 * сколько это заняло и какие уровни взял.
 */
class BuildSessionList
{
    private const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function exec(User $user, ?string $character = null, ?string $realm = null): LengthAwarePaginator
    {
        $sessions = GameSession::query()
            ->where('user_id', $user->id)
            ->when($character !== null, fn ($query) => $query->where('character', $character))
            ->when($realm !== null, fn ($query) => $query->where('realm', $realm))
            ->withCount('wayPoints')
            ->latest('session_start_at')
            ->paginate(self::PER_PAGE);

        $ids = collect($sessions->items())->pluck('id')->all();

        $route = $this->routeEdges($ids);
        $durations = $this->durations($ids);
        $levels = $this->levels($ids);

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = $sessions->through(fn (GameSession $session) => [
            'id' => $session->id,
            'game_session_id' => $session->game_session_id,
            'character' => $session->character,
            'realm' => $session->realm,
            'class' => $session->class,
            'points_count' => $session->way_points_count,
            'session_start_at' => $session->session_start_at->toIso8601String(),
            'duration' => $durations[$session->id] ?? 0,
            'zones' => $route[$session->id] ?? [],
            'level_from' => $levels[$session->id]['from'] ?? $session->level,
            'level_to' => $levels[$session->id]['to'] ?? $session->level,
        ]);

        return $rows;
    }

    /**
     * @return array<int, array{character: string, realm: string, sessions: int}>
     */
    public function characters(User $user): array
    {
        return GameSession::query()
            ->where('user_id', $user->id)
            ->selectRaw('`character`, realm, count(*) as sessions, max(session_start_at) as last_seen')
            ->groupBy('character', 'realm')
            ->orderByDesc('last_seen')
            ->get()
            ->map(fn (GameSession $row) => [
                'character' => (string) $row->getAttribute('character'),
                'realm' => (string) $row->getAttribute('realm'),
                'sessions' => (int) $row->getAttribute('sessions'),
            ])
            ->all();
    }

    /**
     * Первая и последняя карта маршрута — из них складывается заголовок
     * «откуда → куда».
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<int, string>>
     */
    private function routeEdges(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $edges = DB::table('way_points')
            ->whereIn('game_session_id', $ids)
            ->whereNotNull('map_id')
            ->selectRaw('game_session_id, min(sequence) as first_sequence, max(sequence) as last_sequence')
            ->groupBy('game_session_id')
            ->get();

        if ($edges->isEmpty()) {
            return [];
        }

        $wanted = [];

        foreach ($edges as $edge) {
            $wanted[$edge->game_session_id.':'.$edge->first_sequence] = true;
            $wanted[$edge->game_session_id.':'.$edge->last_sequence] = true;
        }

        $maps = [];

        DB::table('way_points')
            ->whereIn('game_session_id', $ids)
            ->whereNotNull('map_id')
            ->select(['game_session_id', 'sequence', 'map_id'])
            ->orderBy('game_session_id')
            ->orderBy('sequence')
            ->cursor()
            ->each(function (object $point) use (&$maps, $wanted) {
                if (isset($wanted[$point->game_session_id.':'.$point->sequence])) {
                    $maps[$point->game_session_id][] = (int) $point->map_id;
                }
            });

        $names = $this->mapNames(array_merge(...array_values($maps) ?: [[]]));
        $result = [];

        foreach ($maps as $sessionId => $mapIds) {
            $result[$sessionId] = array_values(array_unique(array_filter([
                $names[$mapIds[0]] ?? null,
                $names[end($mapIds)] ?? null,
            ])));
        }

        return $result;
    }

    /**
     * @param  array<int, int>  $mapIds
     * @return array<int, string>
     */
    private function mapNames(array $mapIds): array
    {
        return Map::query()
            ->whereIn('id', array_values(array_unique(array_filter($mapIds))))
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function durations(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $tracked = WayPoint::query()
            ->whereIn('game_session_id', $ids)
            ->selectRaw('game_session_id, max(`time`) as tracked')
            ->groupBy('game_session_id')
            ->pluck('tracked', 'game_session_id')
            ->map(fn ($value) => (int) round((int) $value / 10))
            ->all();

        foreach ($this->offline($ids) as $sessionId => $seconds) {
            $tracked[$sessionId] = max(0, ($tracked[$sessionId] ?? 0) - $seconds);
        }

        return $tracked;
    }

    /**
     * Отрезок между выходом из игры и следующим входом временем игры не
     * считается: аддон пишет его длину в событие `gap`.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function offline(array $ids): array
    {
        $offline = [];

        Event::query()
            ->whereIn('game_session_id', $ids)
            ->where('event_type_id', EventTypeEnum::GAP->value)
            ->select(['game_session_id', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$offline) {
                $payload = $event->getAttribute('payload');

                if (! is_array($payload) || ! isset($payload['seconds'])) {
                    return;
                }

                $id = (int) $event->getAttribute('game_session_id');
                $offline[$id] = ($offline[$id] ?? 0) + (int) $payload['seconds'];
            });

        return $offline;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{from: int, to: int}>
     */
    private function levels(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $reached = [];

        Event::query()
            ->whereIn('game_session_id', $ids)
            ->where('event_type_id', EventTypeEnum::LEVELUP->value)
            ->select(['game_session_id', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$reached) {
                $payload = $event->getAttribute('payload');

                if (! is_array($payload) || ! isset($payload['level'])) {
                    return;
                }

                $reached[(int) $event->getAttribute('game_session_id')][] = (int) $payload['level'];
            });

        $levels = [];

        foreach ($reached as $id => $values) {
            // Событие «взял 66-й» означает, что начинал игрок с 65-го.
            $levels[$id] = ['from' => min($values) - 1, 'to' => max($values)];
        }

        return $levels;
    }
}
