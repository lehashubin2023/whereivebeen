<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildImportBatchList;
use App\Enums\GameSession\ImportBatchStateEnum;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportBatch;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildImportBatchListTest extends TestCase
{
    use RefreshDatabase;

    private function batch(User $user, ImportBatchStatusEnum $status, int $queued = 0): ImportBatch
    {
        return ImportBatch::create([
            'user_id' => $user->id,
            'filename' => 'WhereIveBeen.lua',
            'file_size' => 1024,
            'status' => $status,
            'sessions_found' => $queued,
            'sessions_queued' => $queued,
        ]);
    }

    private function log(User $user, ImportBatch $batch, ImportStatusEnum $status): void
    {
        ImportLog::create([
            'user_id' => $user->id,
            'import_batch_id' => $batch->id,
            'status' => $status,
        ]);
    }

    public function test_a_freshly_uploaded_file_is_queued(): void
    {
        $user = User::factory()->create();
        $this->batch($user, ImportBatchStatusEnum::NEW);

        $rows = app(BuildImportBatchList::class)->exec($user)->items();

        $this->assertSame(ImportBatchStateEnum::QUEUED->value, $rows[0]['state']);
        $this->assertFalse($rows[0]['settled']);
    }

    public function test_a_file_with_sessions_still_in_the_queue_is_importing(): void
    {
        $user = User::factory()->create();
        $batch = $this->batch($user, ImportBatchStatusEnum::DISPATCHED, 3);

        $this->log($user, $batch, ImportStatusEnum::COMPLETED);
        $this->log($user, $batch, ImportStatusEnum::IN_PROCESS);

        $rows = app(BuildImportBatchList::class)->exec($user)->items();

        $this->assertSame(ImportBatchStateEnum::IMPORTING->value, $rows[0]['state']);
        $this->assertSame(1, $rows[0]['sessions_finished']);
        $this->assertFalse($rows[0]['settled']);
    }

    public function test_a_file_whose_sessions_all_finished_is_completed(): void
    {
        $user = User::factory()->create();
        $batch = $this->batch($user, ImportBatchStatusEnum::DISPATCHED, 2);

        $this->log($user, $batch, ImportStatusEnum::COMPLETED);
        $this->log($user, $batch, ImportStatusEnum::FAILED);

        $rows = app(BuildImportBatchList::class)->exec($user)->items();

        $this->assertSame(ImportBatchStateEnum::COMPLETED->value, $rows[0]['state']);
        $this->assertSame(2, $rows[0]['sessions_finished']);
        $this->assertSame(1, $rows[0]['sessions_failed']);
        $this->assertTrue($rows[0]['settled']);
    }

    public function test_a_file_where_every_session_was_skipped_is_completed(): void
    {
        $user = User::factory()->create();
        $this->batch($user, ImportBatchStatusEnum::DISPATCHED, 0);

        $rows = app(BuildImportBatchList::class)->exec($user)->items();

        $this->assertSame(ImportBatchStateEnum::COMPLETED->value, $rows[0]['state']);
    }

    public function test_a_broken_file_stays_failed(): void
    {
        $user = User::factory()->create();
        $this->batch($user, ImportBatchStatusEnum::FAILED, 0);

        $rows = app(BuildImportBatchList::class)->exec($user)->items();

        $this->assertSame(ImportBatchStateEnum::FAILED->value, $rows[0]['state']);
        $this->assertTrue($rows[0]['settled']);
    }

    public function test_only_own_batches_are_listed(): void
    {
        $user = User::factory()->create();
        $this->batch(User::factory()->create(), ImportBatchStatusEnum::DISPATCHED, 1);

        $this->assertSame([], app(BuildImportBatchList::class)->exec($user)->items());
    }

    public function test_logs_of_another_batch_do_not_count(): void
    {
        $user = User::factory()->create();
        $batch = $this->batch($user, ImportBatchStatusEnum::DISPATCHED, 2);
        $other = $this->batch($user, ImportBatchStatusEnum::DISPATCHED, 1);

        $this->log($user, $other, ImportStatusEnum::COMPLETED);
        $this->log($user, $batch, ImportStatusEnum::COMPLETED);

        $rows = collect(app(BuildImportBatchList::class)->exec($user)->items())->keyBy('id');

        $this->assertSame(ImportBatchStateEnum::IMPORTING->value, $rows[$batch->id]['state']);
        $this->assertSame(ImportBatchStateEnum::COMPLETED->value, $rows[$other->id]['state']);
    }
}
