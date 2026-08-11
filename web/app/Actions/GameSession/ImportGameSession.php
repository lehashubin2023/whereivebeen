<?php

namespace App\Actions\GameSession;

use App\Exceptions\InvalidGameSessionJsonException;
use App\Validators\GameSessionJsonValidator;

class ImportGameSession
{
    public function exec(array $input): void
    {
        if (empty($input)) {
            throw new InvalidGameSessionJsonException();
        }

        GameSessionJsonValidator::validate($input); 
    }
}
