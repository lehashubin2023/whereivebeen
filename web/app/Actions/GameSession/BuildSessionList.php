<?php

namespace App\Actions\GameSession;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildSessionList
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function exec(User $user, ?string $character = null, ?string $realm = null): LengthAwarePaginator
    {
        $sessions = GameSession::query()
            ->where('user_id', $user->id)
            ->when($character !== null, fn ($query) => $query->where('character', $character))
            ->when($realm !== null, fn ($query) => $query->where('realm', $realm))
            ->latest('session_start_at')
            ->paginate((int) config('pagination.per_page'))
            ->withQueryString();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = $sessions->through(fn (GameSession $session) => [
            'id' => $session->id,
            'game_session_id' => $session->game_session_id,
            'character' => $session->character,
            'realm' => $session->realm,
            'class' => $session->class,
            'points_count' => $session->points_count,
            'session_start_at' => $session->session_start_at->toIso8601String(),
            'duration' => $session->duration_seconds,
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
}
