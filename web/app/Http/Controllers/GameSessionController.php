<?php

namespace App\Http\Controllers;

use App\Actions\GameSession\BuildSessionZones;
use App\Actions\GameSession\ShowSessionEvent;
use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Jobs\ImportGameSessionJob;
use App\Models\GameSession;
use App\Models\ImportLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware(['auth', 'deny-admins'])]
#[Group(prefix: 'game-session', as: 'game-session.')]
class GameSessionController extends Controller
{
    public function __construct(
        private readonly BuildSessionZones $buildSessionZones,
        private readonly ShowSessionEvent $showSessionEvent,
    ) {}

    #[Post('import', name: 'import.store')]
    public function import(ImportGameSessionRequest $request): RedirectResponse
    {
        ImportGameSessionJob::dispatch($request->game_session, $request->user());

        return to_route('game-session.imports');
    }

    #[Get('imports', name: 'imports')]
    public function index(): Response
    {
        $imports = ImportLog::query()
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(20)
            ->through(fn (ImportLog $log) => [
                'id' => $log->id,
                'status' => $log->status->value,
                'points_total' => $log->points_total,
                'points_done' => $log->points_done,
                'execution_time' => (float) $log->execution_time,
                'error_message' => $log->error_message,
                'game_session_id' => $log->game_session_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('game-session/Imports', [
            'imports' => $imports,
        ]);
    }

    #[Get('sessions', name: 'sessions')]
    public function sessions(): Response
    {
        $sessions = GameSession::query()
            ->where('user_id', auth()->id())
            ->withCount('wayPoints')
            ->latest('session_start_at')
            ->paginate(20)
            ->through(fn (GameSession $session) => [
                'id' => $session->id,
                'game_session_id' => $session->game_session_id,
                'character' => $session->character,
                'realm' => $session->realm,
                'points_count' => $session->way_points_count,
                'session_start_at' => $session->session_start_at->toIso8601String(),
            ]);

        return Inertia::render('game-session/Sessions', [
            'sessions' => $sessions,
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
