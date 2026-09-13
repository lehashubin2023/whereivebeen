<?php

namespace App\Exceptions\Lua;

use App\Exceptions\GameSession\ImportException;

class LuaLimitExceededException extends ImportException
{
    protected string $errorCode = 'import.lua_too_large';

    public static function depth(int $maxDepth, int $line): self
    {
        $exception = new self(sprintf('Table nesting is deeper than %d levels', $maxDepth));
        $exception->errorCode = 'import.lua_too_deep';
        $exception->context = ['limit' => $maxDepth, 'line' => $line];

        return $exception;
    }

    public static function entries(int $maxEntries, int $line): self
    {
        $exception = new self(sprintf('A table holds more than %d entries', $maxEntries));
        $exception->context = ['limit' => $maxEntries, 'line' => $line];

        return $exception;
    }

    public static function stringLength(int $maxLength, int $line): self
    {
        $exception = new self(sprintf('A string is longer than %d characters', $maxLength));
        $exception->context = ['limit' => $maxLength, 'line' => $line];

        return $exception;
    }

    public static function bytes(int $maxBytes): self
    {
        $exception = new self(sprintf('The file is larger than %d bytes', $maxBytes));
        $exception->errorCode = 'import.file_too_large';
        $exception->context = ['limit' => $maxBytes];

        return $exception;
    }
}
