<?php

namespace App\DTOs\Addon;

class AddonArchiveData
{
    public function __construct(
        private readonly ?string $version,
        private readonly string $file,
        private readonly string $url,
        private readonly ?int $size,
    ) {}

    /**
     * @return array{version: string|null, file: string, url: string, size: int|null}
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'file' => $this->file,
            'url' => $this->url,
            'size' => $this->size,
        ];
    }
}
