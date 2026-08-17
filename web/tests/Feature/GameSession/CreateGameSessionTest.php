<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\DecodeRawInput;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\InvalidGameSessionInputException;
use App\Exceptions\GameSession\LimitGameSessionsExceededException;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_limit_games_sessions_are_exceeded()
    {
        $this->expectException(LimitGameSessionsExceededException::class);

        $user = User::factory()->create();
        GameSession::factory(10)->create();
        $session = GameSession::factory()->create();

        app()->make(CreateGameSession::class)->exec(
            CreateGameSessionDTO::fromArray($session->toArray()), 
            $user
        );
    }

    public function test_creating_limit_exceeded()
    {

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
