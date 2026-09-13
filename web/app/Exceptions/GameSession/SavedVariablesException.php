<?php

namespace App\Exceptions\GameSession;

class SavedVariablesException extends ImportException
{
    protected string $errorCode = 'import.saved_variables';

    public static function globalNotFound(?string $found): self
    {
        $exception = new self('The file does not define WhereIveBeenDB');
        $exception->errorCode = 'import.lua_global_not_found';
        $exception->context = ['found' => $found];

        return $exception;
    }

    public static function noSessions(): self
    {
        $exception = new self('The file holds no sessions');
        $exception->errorCode = 'import.lua_no_sessions';

        return $exception;
    }

    public static function tooManySessions(int $limit): self
    {
        $exception = new self(sprintf('The file holds more than %d sessions', $limit));
        $exception->errorCode = 'import.too_many_sessions';
        $exception->context = ['limit' => $limit];

        return $exception;
    }

    public static function tooManyPoints(int $limit, int|string $sessionId): self
    {
        $exception = new self(sprintf('A session holds more than %d points', $limit));
        $exception->errorCode = 'import.too_many_points';
        $exception->context = ['limit' => $limit, 'session_id' => (string) $sessionId];

        return $exception;
    }

    public static function unreadable(): self
    {
        $exception = new self('The file cannot be read');
        $exception->errorCode = 'import.file_unreadable';

        return $exception;
    }
}
