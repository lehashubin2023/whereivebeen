<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSession\ImportGameSessionRequest;
use App\Jobs\ImportGameSessionJob;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Prefix;

#[Middleware('auth')]
#[Prefix('game-sessions')]
class GameSessionController extends Controller
{
    #[Get('import')]
    public function showImportPage()
    {
        //
    }

    #[Post('import')]
    public function import(ImportGameSessionRequest $request)
    {
        $input = $request->input('game_session');

        ImportGameSessionJob::dispatch($input);
    }
}
