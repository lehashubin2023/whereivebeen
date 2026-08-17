<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Jobs\ImportGameSessionJob;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Group;
use Spatie\RouteAttributes\Attributes\Middleware;
use Spatie\RouteAttributes\Attributes\Post;

#[Middleware('auth')]
#[Group(prefix: 'game-session', as: 'game-session.')]
class GameSessionController extends Controller
{
    #[Get('import')]
    public function showImportPage()
    {
        //
    }

    #[Post('import', name: "import")]
    public function import(ImportGameSessionRequest $request)
    {
        ImportGameSessionJob::dispatch($request->game_session, auth()->user());
    }
}
