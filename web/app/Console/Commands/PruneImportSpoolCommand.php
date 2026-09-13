<?php

namespace App\Console\Commands;

use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * При `tries = 1` упавший воркер оставляет спул сессии на диске: подобрать его
 * уже некому, поэтому старые каталоги подчищаются по расписанию.
 */
class PruneImportSpoolCommand extends Command
{
    protected $signature = 'imports:prune-spool {--hours=24 : Сколько часов хранить файлы}';

    protected $description = 'Delete leftover import spool files';

    public function handle(): int
    {
        $disk = Storage::disk(SessionSpool::DISK);
        $threshold = now()->subHours((int) $this->option('hours'))->getTimestamp();
        $removed = 0;

        foreach ($disk->directories(SessionSpool::DIR) as $directory) {
            $files = $disk->allFiles($directory);
            $newest = 0;

            foreach ($files as $file) {
                $newest = max($newest, $disk->lastModified($file));
            }

            if ($files !== [] && $newest > $threshold) {
                continue;
            }

            $disk->deleteDirectory($directory);
            $removed++;
        }

        $this->info(sprintf('Removed %d spool directories', $removed));

        return self::SUCCESS;
    }
}
