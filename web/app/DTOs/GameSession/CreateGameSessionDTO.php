<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Http\Request;

class CreateGameSessionDTO implements DTOContract
{
    public function __construct(
        public readonly ?int $user_id,
        public readonly string $character,
        public readonly int $game_session_id,
        public readonly int $session_start_at,
        public readonly ImportStatusEnum $import_status,
        public readonly ?string $import_error_message,
        public readonly string $version,
        public readonly string $realm,
        public readonly int $execution_time
    )
    {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    public static function fromArray(array $data): self
    {
        
        return new self(
            user_id: $data['user_id'] ?? null,
            character: $data['char'],
            game_session_id: $data['sessionID'],
            session_start_at: date('Y-m-d H:i:s', $data['started']),
            import_status: $data['import_status'] ? ImportStatusEnum::from($data['import_status']) : ImportStatusEnum::NEW,
            import_error_message: $data['import_error_message'] ?? null,
            version: $data['version'],
            realm: $data['realm'],
            execution_time: $data['execution_time'] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'character' => $this->character,
            'game_session_id' => $this->game_session_id,
            'session_start_at' => $this->session_start_at,
            'import_status' => $this->import_status,
            'import_error_message' => $this->import_error_message,
            'version' => $this->version,
            'realm' => $this->realm,
            'execution_time' => $this->execution_time
        ];
    }
}
