<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
            $levels[$id] = ['from' => min($values) - 1, 'to' => max($values)];
        }

        return $levels;
    }
}
