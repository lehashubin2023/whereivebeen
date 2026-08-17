<?php

namespace App\Actions\GameSession;

use App\Exceptions\GameSession\InvalidGameSessionInputException;

class DecodeRawInput
{
    public function exec(string $input): array
    {
        $decodedBase64 = base64_decode($input, true);

        if ($decodedBase64 === false) {
            throw new InvalidGameSessionInputException('Invalid base64 input');
        }

        $decodedJson = json_decode($decodedBase64, true, 3);

        if (is_null($decodedJson)) {
            throw new InvalidGameSessionInputException('Invalid JSON input');
        }

        return $decodedJson;
    }
}
