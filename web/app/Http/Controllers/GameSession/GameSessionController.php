<?php

namespace App\Http\Controllers\GameSession;

use App\Actions\GameSession\BuildSessionList;
use App\Actions\GameSession\BuildSessionZones;
use App\Actions\GameSession\ShowSessionEvent;
use App\Http\Controllers\Controller;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;

#[Middleware(['auth', 'deny-admins'])]
#[Group(prefix: 'game-session', as: 'game-session.')]
class GameSessionController extends Controller
{
    public function __construct(
        private readonly BuildSessionZones $buildSessionZones,
        private readonly BuildSessionList $buildSessionList,
        private readonly ShowSessionEvent $showSessionEvent,
    ) {}

    #[Get('sessions', name: 'sessions')]
    public function sessions(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $character = $request->string('character')->toString() ?: null;
        $realm = $request->string('realm')->toString() ?: null;

        return Inertia::render('game-session/Sessions', [
            'sessions' => $this->buildSessionList->exec($user, $character, $realm),
            'characters' => $this->buildSessionList->characters($user),
            'filters' => ['character' => $character, 'realm' => $realm],
        ]);
    }

    #[Get('sessions/{gameSession}', name: 'sessions.show')]
    public function showSession(GameSession $gameSession): Response
    {
        abort_unless($gameSession->user_id === auth()->id(), 403);

        return Inertia::render('game-session/Session', [
            'session' => [
                'id' => $gameSession->id,
                'game_session_id' => $gameSession->game_session_id,
                'character' => $gameSession->character,
                'realm' => $gameSession->realm,
                'session_start_at' => $gameSession->session_start_at->toIso8601String(),
            ],
            'zones' => $this->buildSessionZones->exec($gameSession),
        ]);
    }

    #[Get('sessions/{gameSession}/events/{sequence}', name: 'sessions.event')]
    public function sessionEvent(GameSession $gameSession, int $sequence): JsonResponse
    {
        abort_unless($gameSession->user_id === auth()->id(), 403);

        $event = $this->showSessionEvent->exec($gameSession, $sequence);

        abort_if($event === null, 404);

        return response()->json($event);
    }
}
