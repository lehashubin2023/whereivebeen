<?php

namespace App\Support\GameSession\ImportSource;

use Illuminate\Support\Facades\Storage;

/**
 * Сессия из файла едет в очередь путём на диске, а не телом: два десятка
 * сессий по паре мегабайт иначе оказались бы в Redis одновременно.
 */
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
