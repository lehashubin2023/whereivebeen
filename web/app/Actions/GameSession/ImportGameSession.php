<?php

namespace App\Actions\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\CreateWayPoints;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Models\User;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private CreateWayPoints $createWayPoints
    )
    {}

    public function exec(array $input, User $user): void
    {
        $validated = GameSessionJsonValidator::validate($input);

        try {
            DB::transaction(function () use ($validated, $user) {
                $gameSession = $this->createGameSession->exec(
                    CreateGameSessionDTO::fromArray($validated), 
                    $user
                );

                $this->createWayPoints->exec($gameSession, $validated['points'], $user);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // TODO: should i use a custom exception here? or just report the error and move on?
        }
        
    }
}
