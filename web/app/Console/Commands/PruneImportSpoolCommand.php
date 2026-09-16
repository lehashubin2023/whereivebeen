<?php

namespace App\Console\Commands;

use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class PruneImportSpoolCommand extends Command
{
    protected $signature = 'imports:prune-spool {--hours=24 : Сколько часов хранить файлы}';

    protected $description = 'Delete leftover import spool files and uploads';

    public function handle(): int
    {
        $disk = Storage::disk(SessionSpool::DISK);
        $threshold = now()->subHours((int) $this->option('hours'))->getTimestamp();

        $directories = $this->pruneDirectories($disk, SessionSpool::DIR, $threshold);
        $uploads = $this->pruneUploads($disk, $threshold);

        $this->info(sprintf('Removed %d spool directories and %d uploads', $directories, $uploads));

        return self::SUCCESS;
    }

    private function pruneDirectories(Filesystem $disk, string $root, int $threshold): int
    {
        $removed = 0;

        foreach ($disk->directories($root) as $directory) {
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

        return $removed;
    }

    private function pruneUploads(Filesystem $disk, int $threshold): int
    {
        $removed = 0;

        foreach ($disk->allFiles(SessionSpool::UPLOADS_DIR) as $file) {
            if ($disk->lastModified($file) > $threshold) {
                continue;
            }

            $disk->delete($file);
            $removed++;
        }

        return $removed;
    }
}
