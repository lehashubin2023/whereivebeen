<?php

namespace Tests\Feature\GameSession;

use App\Support\GameSession\SavedVariables\SessionSpool;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneImportSpoolTest extends TestCase
{
    private function age(string $path, int $hours): void
    {
        touch(
            Storage::disk(SessionSpool::DISK)->path($path),
            now()->subHours($hours)->getTimestamp()
        );
    }

    public function test_it_removes_stale_spool_directories_and_uploads(): void
    {
        Storage::fake(SessionSpool::DISK);
        $disk = Storage::disk(SessionSpool::DISK);

        $disk->put(SessionSpool::DIR.'/batch-1/7.jsonl', '{}');
        $disk->put(SessionSpool::UPLOADS_DIR.'/9/old.lua', 'x');
        $this->age(SessionSpool::DIR.'/batch-1/7.jsonl', 48);
        $this->age(SessionSpool::UPLOADS_DIR.'/9/old.lua', 48);

        $this->artisan('imports:prune-spool')->assertSuccessful();

        $disk->assertMissing(SessionSpool::DIR.'/batch-1/7.jsonl');
        $disk->assertMissing(SessionSpool::UPLOADS_DIR.'/9/old.lua');
    }

    public function test_it_keeps_files_that_are_still_fresh(): void
    {
        Storage::fake(SessionSpool::DISK);
        $disk = Storage::disk(SessionSpool::DISK);

        $disk->put(SessionSpool::DIR.'/batch-2/8.jsonl', '{}');
        $disk->put(SessionSpool::UPLOADS_DIR.'/9/fresh.lua', 'x');

        $this->artisan('imports:prune-spool')->assertSuccessful();

        $disk->assertExists(SessionSpool::DIR.'/batch-2/8.jsonl');
        $disk->assertExists(SessionSpool::UPLOADS_DIR.'/9/fresh.lua');
    }
}
