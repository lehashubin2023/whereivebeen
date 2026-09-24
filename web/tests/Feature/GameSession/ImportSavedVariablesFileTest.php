<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\Import\MapImportFailure;
use App\Actions\GameSession\Import\ParseSavedVariablesFile;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Enums\GameSession\ImportStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Jobs\ImportSavedVariablesFileJob;
use App\Models\ImportBatch;
use App\Models\ImportLog;
use App\Models\User;
use App\Models\WayPoint;
use App\Support\GameSession\SavedVariables\SessionSpool;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportSavedVariablesFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(EventTypeSeeder::class);
    }

    public function test_guests_cannot_upload_a_file(): void
    {
        $this->post('/game-session/import-file')->assertRedirect('/login');
    }

    public function test_admins_cannot_upload_a_file(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post('/game-session/import-file')
            ->assertRedirect('/admin/issue-reports');
    }

    public function test_the_file_is_required(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/game-session/import-file')
            ->assertSessionHasErrors('file');
    }

    public function test_an_upload_without_a_file_is_rejected(): void
    {
        Storage::fake(SessionSpool::DISK);

        $this->actingAs(User::factory()->create())
            ->post('/game-session/import-file', [])
            ->assertSessionHasErrors('file');
    }

    public function test_foreign_extensions_are_rejected(): void
    {
        Storage::fake(SessionSpool::DISK);

        $this->actingAs(User::factory()->create())
            ->post('/game-session/import-file', [
                'file' => UploadedFile::fake()->createWithContent('payload.exe', 'nope'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_a_valid_upload_is_queued_and_logged_as_a_batch(): void
    {
        Storage::fake(SessionSpool::DISK);
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/game-session/import-file', [
                'file' => UploadedFile::fake()->createWithContent(
                    'WhereIveBeen.lua',
                    file_get_contents($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua')),
                ),
            ])
            ->assertRedirect('/game-session/imports?tab=files');

        Queue::assertPushed(ImportSavedVariablesFileJob::class);

        $batch = ImportBatch::query()->firstOrFail();

        $this->assertSame($user->id, $batch->user_id);
        $this->assertSame('WhereIveBeen.lua', $batch->filename);
        $this->assertSame(ImportBatchStatusEnum::NEW, $batch->status);
    }

    public function test_a_second_upload_inside_the_window_is_throttled(): void
    {
        Storage::fake(SessionSpool::DISK);
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/game-session/import-file', [
                'file' => UploadedFile::fake()->createWithContent('WhereIveBeen.lua', 'WhereIveBeenDB = {}'),
            ])
            ->assertRedirect('/game-session/imports?tab=files');

        $this->actingAs($user)
            ->postJson('/game-session/import-file', [
                'file' => UploadedFile::fake()->createWithContent('WhereIveBeen.lua', 'WhereIveBeenDB = {}'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('import_batches', 1);
    }

    public function test_the_parser_queues_one_job_per_usable_session(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->batchFor($user, $this->store($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua')));

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $batch['path'], $user, $batch['batch']->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        Queue::assertPushed(ImportGameSessionJob::class, 11);

        $batch['batch']->refresh();

        $this->assertSame(ImportBatchStatusEnum::DISPATCHED, $batch['batch']->status);
        $this->assertSame(11, $batch['batch']->sessions_found);
        $this->assertSame(11, $batch['batch']->sessions_queued);
        $this->assertSame(0, $batch['batch']->sessions_skipped);
    }

    public function test_sessions_without_points_or_character_are_skipped_with_a_reason(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $lua = <<<'LUA'
            WhereIveBeenDB = {
            ["sessions"] = {
            [1700000000001] = {
            ["char"] = "Thrall",
            ["realm"] = "Silvermoon",
            ["started"] = 1700000000,
            ["points"] = {
            { ["x"] = 0.5, ["y"] = 0.25, ["t"] = 0, },
            },
            },
            [1700000000002] = {
            ["char"] = "Thrall",
            ["realm"] = "Silvermoon",
            ["started"] = 1700000000,
            ["points"] = {
            },
            },
            [1700000000003] = {
            ["started"] = 1700000000,
            ["points"] = {
            { ["x"] = 0.5, ["y"] = 0.25, ["t"] = 0, },
            },
            },
            },
            }
            LUA;

        $batch = $this->batchFor($user, $this->storeContents($lua));

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $batch['path'], $user, $batch['batch']->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        Queue::assertPushed(ImportGameSessionJob::class, 1);

        $batch['batch']->refresh();

        $this->assertSame(3, $batch['batch']->sessions_found);
        $this->assertSame(1, $batch['batch']->sessions_queued);
        $this->assertSame(2, $batch['batch']->sessions_skipped);
        $this->assertEqualsCanonicalizing(
            ['no_points', 'no_character'],
            array_column($batch['batch']->skipped, 'reason'),
        );
    }

    public function test_a_damaged_file_fails_the_batch_with_a_readable_code(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $batch = $this->batchFor($user, $this->storeContents('SomeOtherAddonDB = { }'));

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $batch['path'], $user, $batch['batch']->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        Queue::assertNotPushed(ImportGameSessionJob::class);

        $batch['batch']->refresh();

        $this->assertSame(ImportBatchStatusEnum::FAILED, $batch['batch']->status);
        $this->assertSame('import.lua_global_not_found', $batch['batch']->error_code);
        $this->assertSame('SomeOtherAddonDB', $batch['batch']->error_context['found']);
    }

    public function test_the_whole_file_imports_end_to_end(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchFor($user, $this->store($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua')));

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $batch['path'], $user, $batch['batch']->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        $this->assertDatabaseCount('game_sessions', 11);
        $this->assertSame(11, ImportLog::query()->where('status', ImportStatusEnum::COMPLETED)->count());
        $this->assertSame(3514, WayPoint::query()->count());

        $logs = ImportLog::query()->get();

        foreach ($logs as $log) {
            $this->assertSame($batch['batch']->id, $log->import_batch_id);
        }

        $this->assertSame(0, ImportLog::query()->whereNull('outcome')->count());
    }

    public function test_every_session_numbers_its_points_from_one(): void
    {
        $user = User::factory()->create();
        $batch = $this->batchFor($user, $this->store($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua')));

        (new ImportSavedVariablesFileJob(SessionSpool::DISK, $batch['path'], $user, $batch['batch']->id))
            ->handle(app(ParseSavedVariablesFile::class), app(MapImportFailure::class));

        $firstSequences = WayPoint::query()
            ->selectRaw('game_session_id, min(sequence) as first_sequence')
            ->groupBy('game_session_id')
            ->pluck('first_sequence')
            ->unique()
            ->all();

        $this->assertSame([1], array_values($firstSequences));
    }

    /**
     * @return array{batch: ImportBatch, path: string}
     */
    private function batchFor(User $user, string $path): array
    {
        return [
            'batch' => ImportBatch::create([
                'user_id' => $user->id,
                'filename' => 'WhereIveBeen.lua',
                'file_size' => Storage::disk(SessionSpool::DISK)->size($path),
                'status' => ImportBatchStatusEnum::NEW,
            ]),
            'path' => $path,
        ];
    }

    private function store(string $fixturePath): string
    {
        return $this->storeContents(file_get_contents($fixturePath));
    }

    private function storeContents(string $contents): string
    {
        $path = 'imports/uploads/test/'.uniqid('wivb', true).'.lua';

        Storage::disk(SessionSpool::DISK)->put($path, $contents);

        return $path;
    }
}
