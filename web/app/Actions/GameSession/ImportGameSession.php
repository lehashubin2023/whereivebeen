<?php

namespace App\Actions\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Validators\GameSessionJsonValidator;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession
    )
    {}

    public function exec(array $input): void
    {
        $validated = GameSessionJsonValidator::validate($input);

        $this->createGameSession->exec(
            CreateGameSessionDTO::fromArray($validated), 
            auth()->user()
        );
    }
}
