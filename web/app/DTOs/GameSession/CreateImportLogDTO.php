<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Http\Request;

class CreateImportLogDTO implements DTOContract
{
    public function __construct(
        public readonly ?int $user_id,
        public readonly ?int $game_session_id,
        public readonly ImportStatusEnum $status,
        public readonly int $execution_time,
        public readonly ?string $error_message
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
            game_session_id: $data['game_session_id'] ?? null,
            status: isset($data['status']) ? ImportStatusEnum::from($data['status']) : ImportStatusEnum::NEW,
            execution_time: $data['execution_time'] ?? 0,
            error_message: $data['error_message'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'game_session_id' => $this->game_session_id,
            'status' => $this->status,
            'execution_time' => $this->execution_time,
            'error_message' => $this->error_message
        ];
    }
}
