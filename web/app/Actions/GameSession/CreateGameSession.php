<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\AlreadyExistsGameSessionException;
use App\Models\GameSession;
use App\Models\User;

class CreateGameSession
{
    public function exec(CreateGameSessionDTO $dto, User $user): GameSession
    {
        // TODO: Add limit check for the number of game sessions a user can create
        if (false) {
            // throw new LimitExceededException('Game session limit exceeded');
        }

        $data = $dto->toArray();
        
        $alredyExist = GameSession::where('user_id', $user->id)
            ->where('game_session_id', $data['sessionId'])
            ->count();

        if ($alredyExist) {
            throw new AlreadyExistsGameSessionException();
        }

        return GameSession::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }
}
