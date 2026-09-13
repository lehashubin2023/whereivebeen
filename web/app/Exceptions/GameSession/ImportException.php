<?php

namespace App\Exceptions\GameSession;

use App\Exceptions\HasErrorCode;
use Exception;

abstract class ImportException extends Exception implements HasErrorCode
{
    /**
     * @var array<string, scalar|null>
     */
    protected array $context = [];

    protected string $errorCode = 'import.unexpected';

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function errorContext(): array
    {
        return $this->context;
    }
}
