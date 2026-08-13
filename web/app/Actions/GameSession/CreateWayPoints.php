<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Models\GameSession;
use App\Models\User;

class CreateWayPoints
{
    public function exec(GameSession $gameSession, array $waypoints, User $user): void
    {
        foreach ($waypoints as $waypoint) {
            // TODO: Implement way point creation logic
        }
    }
}
