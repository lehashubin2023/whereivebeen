<?php

namespace App\Console\Commands;

use App\Actions\Addon\PackageAddon;
use App\Exceptions\Addon\AddonSourceNotFoundException;
use App\Exceptions\Addon\InvalidAddonTocException;
use Illuminate\Console\Command;

class PackageAddonCommand extends Command
{
    protected $signature = 'addon:package
        {--source= : Addon sources directory (defaults to config addon.source)}
        {--addon-version= : Override the version taken from the .toc}';

    protected $description = 'Package the WoW addon into a zip archive served from public/';

    public function handle(PackageAddon $packageAddon): int
    {
        $source = $this->option('source');
        $version = $this->option('addon-version');

        try {
            $archive = $packageAddon->exec(
                is_string($source) && $source !== '' ? $source : $this->configuredSource(),
                is_string($version) && $version !== '' ? $version : null
            );
        } catch (AddonSourceNotFoundException|InvalidAddonTocException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Packaged %s (%s KB)',
            $archive,
            number_format(((int) filesize($archive)) / 1024, 1)
        ));

        return self::SUCCESS;
    }

    private function configuredSource(): string
    {
        $source = config('addon.source');

        return is_string($source) ? $source : base_path('../addon');
    }
}
