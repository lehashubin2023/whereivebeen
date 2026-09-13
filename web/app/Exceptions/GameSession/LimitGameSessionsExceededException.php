<?php

namespace App\Exceptions\GameSession;

class LimitGameSessionsExceededException extends ImportException
{
    protected $message = 'Create limits are exceeded';

    protected string $errorCode = 'import.session_limit';
}
