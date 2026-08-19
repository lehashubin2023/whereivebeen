<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\DecodeRawInput;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\AlreadyExistsGameSessionException;
use App\Exceptions\GameSession\InvalidGameSessionInputException;
use App\Exceptions\GameSession\LimitGameSessionsExceededException;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateGameSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_limit_games_sessions_are_exceeded()
    {
        $this->expectException(LimitGameSessionsExceededException::class);

        $user = User::factory()->create();
        GameSession::factory(10)->forUser($user)->create();
        $session = GameSession::factory()->create();

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session), 
            $user
        );
    }

    public function test_game_session_already_exists()
    {
        $this->expectException(AlreadyExistsGameSessionException::class);

        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromModel($session), 
            $user
        );
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
