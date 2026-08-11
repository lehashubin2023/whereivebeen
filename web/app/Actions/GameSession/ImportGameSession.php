<?php

namespace App\Actions\GameSession;

use App\Exceptions\InvalidGameSessionJsonException;
use App\Models\GameSession;
use App\Validators\GameSessionJsonValidator;

class ImportGameSession
{
    public function exec(array $input): void
    {
        $validated = GameSessionJsonValidator::validate($input);

        
    }
}
