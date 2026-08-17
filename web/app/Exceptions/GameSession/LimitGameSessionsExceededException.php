<?php

namespace App\Exceptions\GameSession;

use Exception;

class LimitGameSessionsExceededException extends Exception
{
    protected $message = 'Create limits are exceeded';
}
