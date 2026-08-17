<?php

namespace App\Exceptions\GameSession;

use Exception;

class AlreadyExistsGameSessionException extends Exception
{
    protected $message = 'This game session already exists';
}
