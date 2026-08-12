<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Models\Character;
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
    
        GameSession::create([
            ...$dto->toArray(),
            'user_id'       => $user->id,
        ]);
    }
}
