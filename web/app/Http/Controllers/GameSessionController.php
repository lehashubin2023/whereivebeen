<?php

namespace App\Http\Controllers;

use App\Actions\GameSession\BuildImportBatchList;
use App\Actions\GameSession\BuildSessionList;
use App\Actions\GameSession\BuildSessionZones;
use App\Actions\GameSession\ShowSessionEvent;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Http\Requests\GameSession\ImportSavedVariablesRequest;
use App\Jobs\ImportGameSessionJob;
use App\Jobs\ImportSavedVariablesFileJob;
use App\Models\GameSession;
use App\Models\ImportBatch;
use App\Models\ImportLog;
use App\Models\User;
use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        private readonly BuildSessionList $buildSessionList,
        private readonly BuildImportBatchList $buildImportBatchList,
        private readonly ShowSessionEvent $showSessionEvent,
    ) {}

    #[Post('import', name: 'import.store')]
    public function import(ImportGameSessionRequest $request): RedirectResponse
    {
        ImportGameSessionJob::dispatch($request->game_session, $request->user());

        return to_route('game-session.imports');
    }

    #[Post('import-file', name: 'import.file')]
    public function importFile(ImportSavedVariablesRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $user = $request->user();

        $batch = ImportBatch::create([
            'user_id' => $user->id,
            'filename' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'file_size' => (int) $file->getSize(),
            'status' => ImportBatchStatusEnum::NEW,
        ]);

        $path = (string) $file->store(SessionSpool::UPLOADS_DIR.'/'.$user->id, ['disk' => SessionSpool::DISK]);

        ImportSavedVariablesFileJob::dispatch(SessionSpool::DISK, $path, $user, $batch->id);

        return to_route('game-session.imports');
    }

    #[Get('imports', name: 'imports')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $imports = ImportLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20)
            ->through(fn (ImportLog $log) => [
                'id' => $log->id,
                'status' => $log->status->value,
                'outcome' => $log->outcome?->value,
                'points_total' => $log->points_total,
                'points_done' => $log->points_done,
                'execution_time' => (float) $log->execution_time,
                'error_code' => $log->error_code,
                'error_context' => $log->error_context,
                'error_message' => $log->error_message,
                'warnings' => $log->warnings,
                'import_batch_id' => $log->import_batch_id,
                'game_session_id' => $log->game_session_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('game-session/Imports', [
            'imports' => $imports,
            'batches' => $this->buildImportBatchList->exec($user),
        ]);
    }

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
