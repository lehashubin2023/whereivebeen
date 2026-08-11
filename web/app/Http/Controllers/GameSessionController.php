<?php

namespace App\Http\Controllers;

use App\Http\Requests\GameSession\ParseGameSessionRequest;
use App\Jobs\ParseGameSessionJob;

class GameSessionController extends Controller
{
    public function parse(ParseGameSessionRequest $request)
    {
        $input = $request->input('game_session');
        
        ParseGameSessionJob::dispatch($input);
    }
}
