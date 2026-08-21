<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\ImportStatusEnum;
use App\Models\GameSession;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgress;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ImportGameSessionProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructor_creates_new_import_log()
    {
        new ImportGameSessionProgress();

        $this->assertDatabaseCount('import_logs', 1);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatusEnum::NEW]);
    }

    public function test_process_marks_in_process_with_total()
    {
        (new ImportGameSessionProgress())->process(500);

        $this->assertDatabaseHas('import_logs', [
            'status'       => ImportStatusEnum::IN_PROCESS,
            'points_total' => 500,
        ]);
    }

    public function test_track_records_done_points()
    {
        (new ImportGameSessionProgress())->track(123);

        $this->assertDatabaseHas('import_logs', ['points_done' => 123]);
    }

    public function test_complete_sets_completed_and_links_session()
    {
        $session = GameSession::factory()->create();

        (new ImportGameSessionProgress())->complete($session->id);

        $this->assertDatabaseHas('import_logs', [
            'status'          => ImportStatusEnum::COMPLETED,
            'game_session_id' => $session->id,
        ]);
    }

    public function test_fail_sets_failed_and_error_message()
    {
        (new ImportGameSessionProgress())->fail(new RuntimeException('boom'));

        $this->assertDatabaseHas('import_logs', [
            'status'        => ImportStatusEnum::FAILED,
            'error_message' => 'boom',
        ]);
    }

    public function test_id_returns_created_log_id()
    {
        $progress = new ImportGameSessionProgress();

        $this->assertDatabaseHas('import_logs', ['id' => $progress->id()]);
    }

    public function test_null_progress_writes_nothing()
    {
        $progress = new NullImportGameSessionProgress();
        $progress->process(5);
        $progress->track(2);
        $progress->complete(1);
        $progress->fail(new RuntimeException('x'));

        $this->assertDatabaseCount('import_logs', 0);
    }
}
