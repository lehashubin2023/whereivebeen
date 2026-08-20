<?php

namespace App\Actions\GameSession;

use App\Exceptions\GameSession\InvalidGameSessionInputException;

class DecodeRawInput
{
    public function exec(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidGameSessionInputException('Empty input');
        }

        $decodedJson = json_decode($input, true, 4);

        if (is_null($decodedJson)) {
            throw new InvalidGameSessionInputException('Invalid JSON input');
        }

        return $decodedJson;
    }
}
