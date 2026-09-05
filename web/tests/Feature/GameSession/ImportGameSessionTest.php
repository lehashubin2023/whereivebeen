<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\ImportStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Models\User;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\MapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGameSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);
        $this->seed(MapSeeder::class);
    }

    public function test_import_with_first_session_correctly_ended()
    {
        $user = User::factory()->create();
        $session1 = file_get_contents($this->getFixturesPath('/game-sessions/valid1.txt'));

        ImportGameSessionJob::dispatch($session1, $user);
        $this->assertDatabaseCount('import_logs', 1);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatusEnum::COMPLETED]);
        $this->assertDatabaseCount('game_sessions', 1);
    }

    public function test_import_with_second_session_correctly_ended()
    {
        $user = User::factory()->create();
        $session1 = file_get_contents($this->getFixturesPath('/game-sessions/valid2.txt'));

        ImportGameSessionJob::dispatch($session1, $user);
        $this->assertDatabaseCount('import_logs', 1);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatusEnum::COMPLETED]);
        $this->assertDatabaseCount('game_sessions', 1);
    }

    public function test_import_failed()
    {
        $user = User::factory()->create();
        $session1 = file_get_contents($this->getFixturesPath('/game-sessions/not-valid-session.txt'));

        ImportGameSessionJob::dispatch($session1, $user);
        $this->assertDatabaseCount('import_logs', 1);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatusEnum::FAILED]);
        $this->assertDatabaseCount('game_sessions', 0);
    }
}
