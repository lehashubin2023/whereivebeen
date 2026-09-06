<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\CreateWay;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\LimitGameSessionsExceededException;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\User;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateGameSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);
    }

    public function test_limit_games_sessions_are_exceeded()
    {
        if (User::GAME_SESSIONS_LIMIT <= 0) {
            $this->markTestSkipped('Game session limit is disabled');
        }

        $this->expectException(LimitGameSessionsExceededException::class);

        $user = User::factory()->create();
        GameSession::factory(User::GAME_SESSIONS_LIMIT)->forUser($user)->create();
        $session = GameSession::factory()->create();

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session),
            $user
        );
    }

    public function test_sessions_are_not_limited_by_default()
    {
        $user = User::factory()->create();
        GameSession::factory(25)->forUser($user)->create();

        $this->assertFalse($user->canCreateGameSession());
    }

    public function test_reimport_replaces_the_existing_session()
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $replaced = app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session),
            $user
        );

        $this->assertSame($session->id, $replaced->id);
        $this->assertDatabaseCount((new GameSession)->getTable(), 1);
    }

    public function test_reimport_drops_the_previous_way()
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        app()->make(CreateWay::class)->exec($session, [
            ['x' => 0.1, 'y' => 0.2, 'mapId' => null, 't' => 0],
            ['x' => 0.2, 'y' => 0.3, 'mapId' => null, 'event' => 'combat', 'inCombat' => true, 't' => 1],
        ]);

        $this->assertDatabaseCount((new WayPoint)->getTable(), 2);
        $this->assertDatabaseCount((new Event)->getTable(), 1);

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session),
            $user
        );

        $this->assertDatabaseCount((new WayPoint)->getTable(), 0);
        $this->assertDatabaseCount((new Event)->getTable(), 0);
    }

    public function test_game_session_created_correctly_with_full_data()
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->make();

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session),
            $user
        );

        $this->assertDatabaseHas((new GameSession)->getTable(), ['game_session_id' => $session->game_session_id]);
        $this->assertDatabaseCount((new GameSession)->getTable(), 1);
    }
}
