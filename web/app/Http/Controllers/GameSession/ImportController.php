<?php

namespace App\Http\Controllers\GameSession;

use App\Actions\GameSession\Import\BuildImportBatchList;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Enums\GameSession\ImportTabEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Http\Requests\GameSession\ImportSavedVariablesRequest;
use App\Jobs\ImportGameSessionJob;
use App\Jobs\ImportSavedVariablesFileJob;
use App\Models\ImportBatch;
use App\Models\ImportLog;
use App\Models\User;
use App\Support\GameSession\SavedVariables\SessionSpool;
use App\Support\Lua\LuaParseLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware(['auth', 'verified', 'deny-admins'])]
#[Group(prefix: 'game-session', as: 'game-session.')]
class ImportController extends Controller
{
    private const IMPORTS_PAGE_NAME = 'imports_page';

    public function __construct(
        private readonly BuildImportBatchList $buildImportBatchList,
    ) {}

    #[Get('imports', name: 'imports')]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $imports = ImportLog::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(
                perPage: (int) config('pagination.per_page'),
                pageName: self::IMPORTS_PAGE_NAME,
            )
            ->withQueryString()
            ->through(fn (ImportLog $log) => [
                'id' => $log->id,
                'status' => $log->status->value,
                'outcome' => $log->outcome?->value,
                'points_total' => $log->points_total,
                'points_done' => $log->points_done,
                'execution_time' => (float) $log->execution_time,
                'error_code' => $log->error_code,
                'error_context' => $log->error_context,
                'warnings' => $log->warnings,
                'import_batch_id' => $log->import_batch_id,
                'game_session_id' => $log->game_session_id,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('game-session/Imports', [
            'imports' => $imports,
            'batches' => $this->buildImportBatchList->exec($user),
            'tab' => ImportTabEnum::resolve($request->query('tab'))->value,
            'fileLimitMb' => (int) (LuaParseLimits::DEFAULT_MAX_BYTES / 1024 / 1024),
        ]);
    }

    #[Post('import', name: 'import.store', middleware: 'throttle:import-session')]
    public function store(ImportGameSessionRequest $request): RedirectResponse
    {
        ImportGameSessionJob::dispatch($request->game_session, $request->user());

        return to_route('game-session.imports', ['tab' => ImportTabEnum::SESSIONS->value]);
    }

    #[Post('import-file', name: 'import.file', middleware: 'throttle:import-file')]
    public function storeFile(ImportSavedVariablesRequest $request): RedirectResponse
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

        return to_route('game-session.imports', ['tab' => ImportTabEnum::FILES->value]);
    }
}
