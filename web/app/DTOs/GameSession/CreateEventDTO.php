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
    ) {}

    public static function fromPoint(array $point, int $gameSessionId, int $sequence, int $eventTypeId): self
    {
        $map = [
            'level' => 'level',
            'action' => 'action',
            'title' => 'title',
            'place' => 'place',
            'member' => 'member',
            'in_combat' => 'inCombat',
            'mounted' => 'mounted',
            'on_taxi' => 'onTaxi',
            'quest_id' => 'questId',
            'item_id' => 'itemId',
            'item_name' => 'itemName',
            'count' => 'count',
        ];

        $payload = [];
        foreach ($map as $payloadKey => $pointKey) {
            if (isset($point[$pointKey])) {
                $payload[$payloadKey] = $point[$pointKey];
            }
        }

        return new self(
            game_session_id: $gameSessionId,
            sequence: $sequence,
            event_type_id: $eventTypeId,
            payload: json_encode($payload)
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
            'payload' => $this->payload,
        ];
    }
}
