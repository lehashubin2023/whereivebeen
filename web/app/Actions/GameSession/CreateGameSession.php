<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Exceptions\GameSession\LimitGameSessionsExceededException;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\User;
use App\Models\WayPoint;

class CreateGameSession
{
    public function exec(CreateGameSessionDTO $dto, User $user): GameSession
    {
        $data = $dto->toArray();

        $existing = $user->gameSessions()
            ->where('game_session_id', $data['game_session_id'])
            ->first();

        if ($existing instanceof GameSession) {
            return $this->replace($existing, $data);
        }

        if ($user->canCreateGameSession()) {
            throw new LimitGameSessionsExceededException;
        }

        return GameSession::create([
            ...$data,
            'user_id' => $user->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replace(GameSession $session, array $data): GameSession
    {
        unset($data['user_id']);

        $session->fill($data)->save();

        Event::query()->where('game_session_id', $session->id)->delete();
        WayPoint::query()->where('game_session_id', $session->id)->delete();

        return $session;
    }
}
