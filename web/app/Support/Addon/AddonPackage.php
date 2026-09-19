<?php

namespace App\Support\Addon;

use App\Exceptions\Addon\AddonSourceNotFoundException;
use App\Exceptions\Addon\InvalidAddonTocException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use ZipArchive;

class AddonPackage
{
    public function name(): string
    {
        $name = config('addon.name');

        return is_string($name) ? $name : 'WhereIveBeen';
    }

    public function directory(): string
    {
        $directory = config('addon.directory');

        return is_string($directory) ? trim($directory, '/') : 'downloads';
    }

    public function source(): string
    {
        $source = config('addon.source');

        return is_string($source) ? $source : base_path('../addon');
    }

    public function archiveName(string $version): string
    {
        return sprintf('%s-%s.zip', $this->name(), $version);
    }

    public function archivePath(string $version): string
    {
        return public_path($this->directory().'/'.$this->archiveName($version));
    }

    public function archivePattern(): string
    {
        return public_path($this->directory().'/'.$this->name().'-*.zip');
    }

    public function versionFromName(string $file): ?string
    {
        $pattern = '/^'.preg_quote($this->name(), '/').'-(.+)\.zip$/';

        return preg_match($pattern, $file, $matches) === 1 ? $matches[1] : null;
    }

    public function latestArchive(): ?string
    {
        $archives = glob($this->archivePattern()) ?: [];

        if ($archives === []) {
            return null;
        }

        usort($archives, fn (string $a, string $b) => version_compare(
            (string) $this->versionFromName(basename($a)),
            (string) $this->versionFromName(basename($b))
        ));

        return end($archives);
    }

    /**
     * @throws AddonSourceNotFoundException
     * @throws InvalidAddonTocException
     */
    public function package(?string $source = null, ?string $version = null): string
    {
        $source = rtrim($source ?? $this->source(), '/\\');

        if (! is_dir($source)) {
            throw new AddonSourceNotFoundException("Addon source directory not found: {$source}");
        }

        $version ??= $this->readVersion($source);
        $target = $this->archivePath($version);

        $this->prepareDirectory();

        $zip = new ZipArchive;

        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new AddonSourceNotFoundException("Unable to create the archive: {$target}");
        }

        foreach ($this->files($source) as $relativePath => $file) {
            $zip->addFile($file->getPathname(), $this->name().'/'.$relativePath);
        }

        $zip->close();

        return $target;
    }

    /**
     * @throws InvalidAddonTocException
     */
    private function readVersion(string $source): string
    {
        $toc = $source.'/'.$this->name().'.toc';

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
        $directory = public_path($this->directory());

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        foreach (glob($this->archivePattern()) ?: [] as $archive) {
            unlink($archive);
        }
    }
}
