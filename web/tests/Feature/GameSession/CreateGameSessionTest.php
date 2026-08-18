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

    // public function test_importer()
    // {
    //     $user = User::factory()->create();
    //     $importer = app()->make(ImportGameSession::class);

    //     $validSession1 = file_get_contents(base_path() . '/tests/Fixtures/game-sessions/session1.txt');
    //     $validSession2 = file_get_contents(base_path() . '/tests/Fixtures/game-sessions/session1.txt');
    //     $notValidSession = file_get_contents(base_path() . '/tests/Fixtures/game-sessions/not-valid-session.txt');

    //     $importer->exec($validSession1, $user);
    // }
}
