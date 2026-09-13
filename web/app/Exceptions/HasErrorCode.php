<?php

namespace App\Exceptions;

interface HasErrorCode
{
    public function errorCode(): string;

    /**
     * @return array<string, scalar|null>
     */
    public function errorContext(): array;
}
