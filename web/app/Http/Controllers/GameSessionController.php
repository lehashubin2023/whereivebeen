<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Jobs\ImportGameSessionJob;
use App\Models\ImportLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware('auth')]
#[Group(prefix: 'game-session', as: 'game-session.')]
class GameSessionController extends Controller
{
    #[Get('import', name: 'import')]
    public function showImportPage(): Response
    {
        return Inertia::render('game-session/Import');
    }

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
}
