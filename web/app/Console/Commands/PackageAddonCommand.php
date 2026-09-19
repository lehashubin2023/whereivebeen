<?php

namespace App\Console\Commands;

use App\Exceptions\Addon\AddonSourceNotFoundException;
use App\Exceptions\Addon\InvalidAddonTocException;
use App\Support\Addon\AddonPackage;
use Illuminate\Console\Command;

class PackageAddonCommand extends Command
{
    protected $signature = 'addon:package
        {--source= : Addon sources directory (defaults to config addon.source)}
        {--addon-version= : Override the version taken from the .toc}';

    protected $description = 'Package the WoW addon into a zip archive served from public/';

    public function handle(AddonPackage $addon): int
    {
        $source = $this->option('source');
        $version = $this->option('addon-version');

        try {
            $archive = $addon->package(
                is_string($source) && $source !== '' ? $source : null,
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
}
