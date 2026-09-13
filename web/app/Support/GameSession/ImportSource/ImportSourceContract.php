<?php

namespace App\Support\GameSession\ImportSource;

interface ImportSourceContract
{
    public function read(): string;

    /**
     * Убрать за собой временные файлы. Для строки — ничего не делает.
     */
    public function release(): void;
}
