<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\AlreadyExistsGameSessionException;
use App\Exceptions\GameSession\LimitGameSessionsExceededException;
use App\Models\GameSession;
use App\Models\User;

class CreateGameSession
{
    public function exec(CreateGameSessionDTO $dto, User $user): GameSession
    {
        if ($user->canCreateGameSession()) {
            throw new LimitGameSessionsExceededException;
        }

        $data = $dto->toArray();

        if ($user->doesGameSessionAlreadyExist($data['game_session_id'])) {
            throw new AlreadyExistsGameSessionException;
        }

        return GameSession::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }
}
