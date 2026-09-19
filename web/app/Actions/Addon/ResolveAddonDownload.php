<?php

namespace App\Actions\Addon;

use App\Support\Addon\AddonPackage;

class ResolveAddonDownload
{
    public function __construct(
        private readonly AddonPackage $addon,
    ) {}

    /**
     * @return array{available: bool, version: string|null, file: string|null, url: string|null, size: int|null}
     */
    public function exec(): array
    {
        $archive = $this->addon->latestArchive();

        if ($archive === null) {
            return [
                'available' => false,
                'version' => null,
                'file' => null,
                'url' => null,
                'size' => null,
            ];
        }

        $file = basename($archive);
        $size = filesize($archive);

        return [
            'available' => true,
            'version' => $this->addon->versionFromName($file),
            'file' => $file,
            'url' => '/'.$this->addon->directory().'/'.$file,
            'size' => $size === false ? null : $size,
        ];
    }
}
