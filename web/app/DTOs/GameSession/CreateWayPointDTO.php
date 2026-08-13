<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Http\Request;

class CreateWayPointDTO implements DTOContract
{
    public function __construct(
        private readonly int $game_session_id,
        private readonly ?int $map_id,
        private readonly int $time,     
        private readonly int $sequence, 
        private readonly int $x,
        private readonly int $y,
        // private readonly int $state
    )
    {}

    public static function fromPoint(array $point, int $gameSessionId, int $sequence): self
    {
        return new self(
            game_session_id: $gameSessionId,
            map_id: $point['map_id'] ?? null,
            time: $point['time'] ?? 0,
            sequence: $sequence,
            x: $point['x'] ?? 0,
            y: $point['y'] ?? 0,
            // state: $point['state'] ?? 0
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
            map_id: $data['map_id'] ?? null,
            time: $data['time'] ?? 0,
            sequence: $data['sequence'] ?? 0,
            x: $data['x'] ?? 0,
            y: $data['y'] ?? 0,
            // state: $data['state'] ?? 0
        );
    }

    public function toArray(): array
    {
        return [
            'game_session_id' => $this->game_session_id,
            'map_id' => $this->map_id,
            'time' => $this->time,
            'sequence' => $this->sequence,
            'x' => $this->x,
            'y' => $this->y,
            'state' => $this->state
        ];
    }
}
