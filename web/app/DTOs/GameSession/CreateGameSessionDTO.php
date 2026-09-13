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
        public readonly ?int $version,
        public readonly string $realm,
        public readonly ?int $session_end_at = null,
        public readonly ?int $continues_from = null,
        public readonly int $schema_version = 1,
        public readonly ?string $addon_version = null,
        public readonly ?string $game_version = null,
        public readonly ?string $locale = null,
        public readonly ?string $faction = null,
        public readonly ?string $class = null,
        public readonly ?int $level = null,
    ) {}

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
            realm: $gameSession->realm,
            session_end_at: $gameSession->session_end_at === null
                ? null
                : (strtotime($gameSession->session_end_at) ?: null),
            continues_from: $gameSession->continues_from,
            schema_version: $gameSession->schema_version ?? 1,
            addon_version: $gameSession->addon_version,
            game_version: $gameSession->game_version,
            locale: $gameSession->locale,
            faction: $gameSession->faction,
            class: $gameSession->class,
            level: $gameSession->level,
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'] ?? null,
            character: $data['char'],
            game_session_id: $data['sessionId'],
            session_start_at: $data['started'],
            version: isset($data['version']) ? (int) $data['version'] : null,
            realm: $data['realm'],
            session_end_at: $data['ended'] ?? null,
            continues_from: $data['continuesFrom'] ?? null,
            schema_version: (int) ($data['schema'] ?? 1),
            addon_version: $data['addon'] ?? null,
            game_version: $data['gameVersion'] ?? null,
            locale: $data['locale'] ?? null,
            faction: $data['faction'] ?? null,
            class: $data['class'] ?? null,
            level: isset($data['level']) ? (int) $data['level'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'character' => $this->character,
            'game_session_id' => $this->game_session_id,
            'session_start_at' => $this->session_start_at,
            'session_end_at' => $this->session_end_at,
            'continues_from' => $this->continues_from,
            'version' => $this->version,
            'game_version' => $this->game_version,
            'addon_version' => $this->addon_version,
            'schema_version' => $this->schema_version,
            'locale' => $this->locale,
            'faction' => $this->faction,
            'class' => $this->class,
            'level' => $this->level,
            'realm' => $this->realm,
        ];
    }
}
