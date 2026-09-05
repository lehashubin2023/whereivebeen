<?php

namespace App\Actions\Addon;

use App\Concerns\AddonPackagePaths;

class ResolveAddonDownload
{
    use AddonPackagePaths;

    /**
     * @return array{available: bool, version: string|null, file: string|null, url: string|null, size: int|null}
     */
    public function exec(): array
    {
        $archive = $this->latestArchive();

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
            'version' => $this->versionFromName($file),
            'file' => $file,
            'url' => '/'.$this->addonDirectory().'/'.$file,
            'size' => $size === false ? null : $size,
        ];
    }

    private function latestArchive(): ?string
    {
        $archives = glob($this->addonArchivePattern()) ?: [];

        if ($archives === []) {
            return null;
        }

        usort($archives, fn (string $a, string $b) => version_compare(
            (string) $this->versionFromName(basename($a)),
            (string) $this->versionFromName(basename($b))
        ));

        return end($archives);
    }

    private function versionFromName(string $file): ?string
    {
        $pattern = '/^'.preg_quote($this->addonName(), '/').'-(.+)\.zip$/';

        return preg_match($pattern, $file, $matches) === 1 ? $matches[1] : null;
    }
}
