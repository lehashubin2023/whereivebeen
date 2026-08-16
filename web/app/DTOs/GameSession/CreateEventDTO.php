<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class CreateEventDTO implements DTOContract
{
    public function __construct(
        private readonly int $game_session_id,
        private readonly int $sequence,
        private readonly ?int $event_type_id,
        private readonly string $payload
    )
    {}

    public static function fromPoint(array $point, int $gameSessionId, int $sequence, int $eventTypeId): self
    {
        return new self(
            game_session_id: $gameSessionId,
            sequence: $sequence,
            event_type_id: $eventTypeId,
            payload: json_encode([
                'level' => $point['level'] ?? null,
                'action' => $point['action'] ?? null,
                'title' => $point['title'] ?? null,
                'place' => $point['place'] ?? null,
                'member' => $point['member'] ?? null,
                'in_combat' => $point['inCombat'] ?? null,
                'mounted' => $point['mounted'] ?? null,
                'on_taxi' => $point['onTaxi'] ?? null,
                'quest_id' => $point['questId'] ?? null,
            ])
        );
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    public static function fromArray(array $data): self
    {
        return new self(
            game_session_id: $data['game_session_id'] ?? null,
            sequence: $data['sequence'] ?? 0,
            event_type_id: $data['event_type_id'] ?? null,
            payload: $data['payload'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'game_session_id' => $this->game_session_id,
            'sequence' => $this->sequence,
            'event_type_id' => $this->event_type_id,
            'payload' => $this->payload
        ];
    }
}
