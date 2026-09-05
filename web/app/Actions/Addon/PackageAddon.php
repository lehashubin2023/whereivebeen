<?php

namespace App\Actions\Addon;

use App\Concerns\AddonPackagePaths;
use App\Exceptions\Addon\AddonSourceNotFoundException;
use App\Exceptions\Addon\InvalidAddonTocException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class PackageAddon
{
    use AddonPackagePaths;

    /**
     * @throws AddonSourceNotFoundException
     * @throws InvalidAddonTocException
     */
    public function exec(string $source, ?string $version = null): string
    {
        $source = rtrim($source, '/\\');

        if (! is_dir($source)) {
            throw new AddonSourceNotFoundException("Addon source directory not found: {$source}");
        }

        $version ??= $this->readVersion($source);
        $target = $this->addonArchivePath($version);

        $this->prepareDirectory();

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new AddonSourceNotFoundException("Unable to create the archive: {$target}");
        }

        foreach ($this->files($source) as $relativePath => $file) {
            $zip->addFile($file->getPathname(), $this->addonName().'/'.$relativePath);
        }

        $zip->close();

        return $target;
    }

    /**
     * @throws InvalidAddonTocException
     */
    private function readVersion(string $source): string
    {
        $toc = $source.'/'.$this->addonName().'.toc';

        if (! is_file($toc)) {
            throw new InvalidAddonTocException("Addon .toc file not found: {$toc}");
        }

        $contents = (string) file_get_contents($toc);

        if (preg_match('/^##\s*Version:\s*(\S+)/mi', $contents, $matches) !== 1) {
            throw new InvalidAddonTocException("No version directive in {$toc}");
        }

        return $matches[1];
    }

    /**
     * @return iterable<string, SplFileInfo>
     */
    private function files(string $source): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));

            if (str_starts_with(basename($relativePath), '.') || str_contains($relativePath, '/.')) {
                continue;
            }

            yield $relativePath => $file;
        }
    }

    private function prepareDirectory(): void
    {
        $directory = public_path($this->addonDirectory());

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (glob($this->addonArchivePattern()) ?: [] as $archive) {
            unlink($archive);
        }
    }
}
