<?php

namespace App\Actions\GameSession;

use App\Exceptions\GameSession\InvalidGameSessionInputException;

class DecodeRawInput
{
    /**
     * Префикс сжатого экспорта аддона (см. addon/Export.lua, WIVBN.EXPORT_PREFIX).
     */
    public const COMPRESSED_PREFIX = 'WIVB1:';

    /**
     * Схлопнутые события добавляют уровень вложенности:
     * root -> points -> point -> items -> item.
     */
    public const MAX_DEPTH = 6;

    public function exec(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidGameSessionInputException('Empty input');
        }

        if (str_starts_with($input, self::COMPRESSED_PREFIX)) {
            $input = $this->inflate(substr($input, strlen(self::COMPRESSED_PREFIX)));
        }

        $decodedJson = json_decode($input, true, self::MAX_DEPTH);

        if (! is_array($decodedJson)) {
            throw new InvalidGameSessionInputException('Invalid JSON input');
        }

        return $decodedJson;
    }

    private function inflate(string $payload): string
    {
        $binary = base64_decode(trim($payload), true);

        if ($binary === false) {
            throw new InvalidGameSessionInputException('Invalid base64 input');
        }

        $json = @gzinflate($binary);

        if ($json === false) {
            throw new InvalidGameSessionInputException('Invalid compressed input');
        }

        return $json;
    }
}
