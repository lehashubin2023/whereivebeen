<?php

namespace App\Support\GameSession\ImportSource;

use Illuminate\Support\Facades\Storage;

final readonly class SpooledImportSource implements ImportSourceContract
{
    public function __construct(private string $disk, private string $path) {}

    public function read(): string
    {
        return (string) Storage::disk($this->disk)->get($this->path);
    }

    public function release(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
