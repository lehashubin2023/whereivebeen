<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\ImportStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImportFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_from_import_page()
    {
        $this->get('/game-session/import')->assertRedirect('/login');
    }

    public function test_import_page_renders()
    {
        $this->actingAs($this->user)
            ->get('/game-session/import')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('game-session/Import'));
    }

    public function test_import_dispatches_job_and_redirects_to_list()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/game-session/import', ['game_session' => '{"points":[]}'])
            ->assertRedirect('/game-session/imports');

        Queue::assertPushed(ImportGameSessionJob::class);
    }

    public function test_import_requires_game_session()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/game-session/import', [])
            ->assertSessionHasErrors('game_session');

        Queue::assertNothingPushed();
    }

    public function test_list_shows_only_current_user_logs()
    {
        ImportLog::create([
            'user_id' => $this->user->id,
            'status' => ImportStatusEnum::COMPLETED,
            'points_total' => 10,
            'points_done' => 10,
            'execution_time' => 1,
        ]);

        ImportLog::create([
            'user_id' => User::factory()->create()->id,
            'status' => ImportStatusEnum::FAILED,
            'error_message' => 'nope',
        ]);

        $this->actingAs($this->user)
            ->get('/game-session/imports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('game-session/Imports')
                ->has('imports.data', 1)
                ->where('imports.data.0.status', 'completed')
            );
    }
}
