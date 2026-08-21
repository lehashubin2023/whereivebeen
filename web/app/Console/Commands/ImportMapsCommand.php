<?php

namespace App\Console\Commands;

use App\Actions\Map\ImportMaps;
use Illuminate\Console\Command;

class ImportMapsCommand extends Command
{
    protected $signature = 'maps:import
        {manifest : Путь к JSON-манифесту карт ([{id,name}] — из аддона /wivbn maps или UiMap.db2)}
        {--images=public/maps : Каталог с изображениями карт (файлы <uiMapID>.png)}
        {--url-prefix=/maps : Префикс URL, из которого раздаются изображения}';

    protected $description = 'Импорт карт (id, name, image_path) в таблицу maps из манифеста и папки изображений';

    public function handle(ImportMaps $importer): int
    {
        $path = $this->argument('manifest');

        if (! is_file($path)) {
            $this->error("Манифест не найден: {$path}");

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest)) {
            $this->error('Манифест не является валидным JSON-массивом');

            return self::FAILURE;
        }

        $imagesDir = $this->option('images');
        // Относительный путь резолвим от корня проекта
        if (! preg_match('#^([a-zA-Z]:|/)#', $imagesDir)) {
            $imagesDir = base_path($imagesDir);
        }

        $stats = $importer->exec($manifest, $imagesDir, $this->option('url-prefix'));

        $this->info(sprintf(
            'Импортировано карт: %d (с изображением: %d), пропущено: %d',
            $stats['total'],
            $stats['withImage'],
            $stats['skipped']
        ));

        return self::SUCCESS;
    }
}
