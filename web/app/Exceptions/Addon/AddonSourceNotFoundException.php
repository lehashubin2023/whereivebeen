<?php

namespace App\Exceptions\Addon;

use Exception;

class AddonSourceNotFoundException extends Exception
{
    protected $message = 'Addon source directory not found';
}
