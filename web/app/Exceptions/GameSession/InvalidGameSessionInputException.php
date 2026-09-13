<?php

namespace App\Exceptions\GameSession;

class InvalidGameSessionInputException extends ImportException
{
    protected $message = 'Invalid input data';

    protected string $errorCode = 'import.invalid_input';

    public static function empty(): self
    {
        return self::withCode('Empty input', 'import.empty_input');
    }

    public static function badJson(): self
    {
        return self::withCode('Invalid JSON input', 'import.invalid_json');
    }

    public static function badBase64(): self
    {
        return self::withCode('Invalid base64 input', 'import.invalid_base64');
    }

    public static function badDeflate(): self
    {
        return self::withCode('Invalid compressed input', 'import.invalid_compressed');
    }

    /**
     * @param  array<string, scalar|null>  $context
     */
    private static function withCode(string $message, string $code, array $context = []): self
    {
        $exception = new self($message);
        $exception->errorCode = $code;
        $exception->context = $context;

        return $exception;
    }
}
