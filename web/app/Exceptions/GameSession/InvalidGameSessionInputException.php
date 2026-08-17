<?php

namespace App\Exceptions\GameSession;

use Exception;

class InvalidGameSessionInputException extends Exception
{
    protected $message = 'Invalid input data';
}
