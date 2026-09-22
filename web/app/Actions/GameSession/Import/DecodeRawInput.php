<?php

namespace App\Actions\GameSession\Import;

use App\Exceptions\GameSession\InvalidGameSessionInputException;

class DecodeRawInput
{
    public const COMPRESSED_PREFIX = 'WIVB1:';

    public const MAX_DEPTH = 6;

    public function exec(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw InvalidGameSessionInputException::empty();
        }

        if (str_starts_with($input, self::COMPRESSED_PREFIX)) {
            $input = $this->inflate(substr($input, strlen(self::COMPRESSED_PREFIX)));
        }

        $decodedJson = json_decode($input, true, self::MAX_DEPTH);

        if (! is_array($decodedJson)) {
            throw InvalidGameSessionInputException::badJson();
        }

        return $decodedJson;
    }

    private function inflate(string $payload): string
    {
        $binary = base64_decode(trim($payload), true);

        if ($binary === false) {
            throw InvalidGameSessionInputException::badBase64();
        }

        $json = @gzinflate($binary);

        if ($json === false) {
            throw InvalidGameSessionInputException::badDeflate();
        }

        return $json;
    }
}
