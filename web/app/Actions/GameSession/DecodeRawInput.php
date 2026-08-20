<?php

namespace App\Actions\GameSession;

use App\Exceptions\GameSession\InvalidGameSessionInputException;

class DecodeRawInput
{
    public function exec(string $input): array
    {
        if (empty($input)) {
            throw new InvalidGameSessionInputException('Empty input');
        }

        $decodedBase64 = base64_decode($input, true);

        if ($decodedBase64 === false) {
            throw new InvalidGameSessionInputException('Invalid base64 input');
        }

        $decodedJson = json_decode($decodedBase64, true, 4);

        if (is_null($decodedJson)) {
            throw new InvalidGameSessionInputException('Invalid JSON input');
        }

        return $decodedJson;
    }
}
