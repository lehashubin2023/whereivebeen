<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use App\Models\GameSession;
use Illuminate\Http\Request;

class CreateGameSessionDTO implements DTOContract
{
    public function __construct(
        public readonly ?int $user_id,
        public readonly string $character,
        public readonly int $game_session_id,
        public readonly int $session_start_at,
        public readonly string $version,
        public readonly string $realm
    )
    {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    public static function fromModel(GameSession $gameSession): self
    {
        return new self(
            user_id: $gameSession->user_id,
            character: $gameSession->character,
            game_session_id: $gameSession->game_session_id,
            session_start_at: strtotime($gameSession->session_start_at),
            version: $gameSession->version,
            realm: $gameSession->realm
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'] ?? null,
            character: $data['char'],
            game_session_id: $data['sessionId'],
            session_start_at: $data['started'],
            version: $data['version'],
            realm: $data['realm']
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'character' => $this->character,
            'game_session_id' => $this->game_session_id,
            'session_start_at' => $this->session_start_at,
            'version' => $this->version,
            'realm' => $this->realm
        ];
    }
}
