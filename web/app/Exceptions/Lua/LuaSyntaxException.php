<?php

namespace App\Exceptions\Lua;

use App\Exceptions\GameSession\ImportException;

class LuaSyntaxException extends ImportException
{
    protected string $errorCode = 'import.lua_syntax';

    public static function at(int $line, string $near): self
    {
        $exception = new self(sprintf('Unexpected input on line %d near "%s"', $line, $near));
        $exception->context = ['line' => $line, 'near' => mb_substr($near, 0, 24)];

        return $exception;
    }

    public static function badNumber(int $line, string $near): self
    {
        $exception = new self(sprintf('Unsupported number on line %d near "%s"', $line, $near));
        $exception->errorCode = 'import.lua_bad_number';
        $exception->context = ['line' => $line, 'near' => mb_substr($near, 0, 24)];

        return $exception;
    }

    public static function unterminated(int $line, string $what): self
    {
        $exception = new self(sprintf('Unterminated %s started on line %d', $what, $line));
        $exception->context = ['line' => $line, 'near' => $what];

        return $exception;
    }
}
