<?php

namespace App\Support\GameSession\ImportSource;

final readonly class InlineImportSource implements ImportSourceContract
{
    public function __construct(private string $raw) {}

    public function read(): string
    {
        return $this->raw;
    }

    public function release(): void {}
}
