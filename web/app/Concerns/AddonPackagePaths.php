<?php

namespace App\Concerns;

trait AddonPackagePaths
{
    protected function addonName(): string
    {
        $name = config('addon.name');

        return is_string($name) ? $name : 'WhereIveBeen';
    }

    protected function addonDirectory(): string
    {
        $directory = config('addon.directory');

        return is_string($directory) ? trim($directory, '/') : 'downloads';
    }

    protected function addonArchivePath(string $version): string
    {
        return public_path($this->addonDirectory().'/'.$this->addonArchiveName($version));
    }

    protected function addonArchiveName(string $version): string
    {
        return sprintf('%s-%s.zip', $this->addonName(), $version);
    }

    protected function addonArchivePattern(): string
    {
        return public_path($this->addonDirectory().'/'.$this->addonName().'-*.zip');
    }
}
