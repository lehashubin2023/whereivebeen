<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Список сессий, из которого понятно, что это была за игра: откуда куда сходил,
 * сколько это заняло и какие уровни взял.
 */
class BuildSessionList
{
    private const PER_PAGE = 20;

    public function __construct(private readonly MeasureSessionTime $measureTime) {}

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
        $durations = $this->measureTime->exec($ids);
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
