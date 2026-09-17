<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\ImportStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Models\GameSession;
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

        $this->withoutVite();

        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_from_imports_page()
    {
        $this->get('/game-session/imports')->assertRedirect('/login');
    }

    public function test_imports_page_renders()
    {
        $this->actingAs($this->user)
            ->get('/game-session/imports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('game-session/Imports'));
    }

    public function test_import_dispatches_job_and_redirects_to_list()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/game-session/import', ['game_session' => '{"points":[]}'])
            ->assertRedirect('/game-session/imports?tab=sessions');

        Queue::assertPushed(ImportGameSessionJob::class);
    }

    public function test_import_requires_game_session()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson('/game-session/import', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('game_session');

        Queue::assertNothingPushed();
    }

    public function test_a_session_over_the_size_limit_is_rejected()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->postJson('/game-session/import', [
                'game_session' => str_repeat('x', GameSession::MAX_IMPORT_SIZE + 1),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('game_session');

        Queue::assertNothingPushed();
    }

    public function test_a_second_paste_inside_the_window_is_throttled()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/game-session/import', ['game_session' => '{"points":[]}'])
            ->assertRedirect('/game-session/imports?tab=sessions');

        $this->actingAs($this->user)
            ->postJson('/game-session/import', ['game_session' => '{"points":[]}'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('game_session');

        Queue::assertPushed(ImportGameSessionJob::class, 1);
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

    public function test_imports_page_opens_the_sessions_tab_by_default()
    {
        $this->actingAs($this->user)
            ->get('/game-session/imports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('game-session/Imports')
                ->where('tab', 'sessions')
                ->has('imports.data')
                ->has('batches.data')
            );
    }

    public function test_imports_page_opens_the_files_tab()
    {
        $this->actingAs($this->user)
            ->get('/game-session/imports?tab=files')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'files'));
    }

    public function test_an_unknown_tab_falls_back_to_sessions()
    {
        $this->actingAs($this->user)
            ->get('/game-session/imports?tab=nope')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('tab', 'sessions'));
    }

    public function test_the_import_list_is_paginated()
    {
        config()->set('pagination.per_page', 2);

        foreach (range(1, 3) as $ignored) {
            ImportLog::create([
                'user_id' => $this->user->id,
                'status' => ImportStatusEnum::COMPLETED,
            ]);
        }

        $this->actingAs($this->user)
            ->get('/game-session/imports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('imports.data', 2)
                ->where('imports.last_page', 2)
            );
    }
}
