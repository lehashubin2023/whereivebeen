<?php

namespace App\Support\GameSession\ImportSource;

interface ImportSourceContract
{
    public function read(): string;

    public function release(): void;
}
