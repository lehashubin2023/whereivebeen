<?php

namespace App\Console\Commands;

use App\Actions\Map\ImportMaps;
use App\Actions\Map\ParseUiMapCsv;
use App\Exceptions\Map\InvalidUiMapCsvException;
use Illuminate\Console\Command;

class ImportMapsCommand extends Command
{
    protected $signature = 'maps:import
        {--source=storage/app/private/UiMap.csv : UiMap.db2 export (CSV: ID;Name_lang)}';

    protected $description = 'Import maps from UiMap and attach each zone image from public/maps by name';

    public function handle(ImportMaps $importer, ParseUiMapCsv $parseCsv): int
    {
        $source = $this->resolvePath($this->option('source'));

        try {
            $rows = $parseCsv->exec($source);
        } catch (InvalidUiMapCsvException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stats = $importer->exec($rows);

        $this->info(sprintf(
            'Imported maps: %d, skipped rows: %d',
            (int) $stats['total'],
            (int) $stats['skipped']
        ));

        return self::SUCCESS;
    }

    private function resolvePath(string $path): string
    {
        return preg_match('#^([a-zA-Z]:|/)#', $path) ? $path : base_path($path);
    }
}
