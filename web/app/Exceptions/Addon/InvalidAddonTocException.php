<?php

namespace App\Exceptions\Addon;

use Exception;

class InvalidAddonTocException extends Exception
{
    protected $message = 'Unable to read the addon version from the .toc file';
}
