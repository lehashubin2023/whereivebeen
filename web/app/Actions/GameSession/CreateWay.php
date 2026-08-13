<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateEventDTO;
use App\DTOs\GameSession\CreateWayPointDTO;
use App\Models\Event;
use App\Models\EventType;
use App\Models\GameSession;
use App\Models\WayPoint;
use Illuminate\Support\Facades\DB;

class CreateWay
{
    const CHUNK_SIZE = 1000;

    private array $eventTypes;

    public function __construct()
    {
        $this->eventTypes = EventType::pluck('id', 'name')->toArray();
    }

    public function exec(GameSession $gameSession, array $waypoints): void
    {
        $waypoints = array_values($waypoints);

        foreach (array_chunk($waypoints, self::CHUNK_SIZE) as $chunk) {
            [$wayPointData, $eventData] = $this->prepareWayData($chunk, $gameSession->id);

            DB::transaction(function () use ($wayPointData, $eventData) {
                WayPoint::insert($wayPointData);
                Event::insert($eventData);
            });
        }
    }

    private function prepareWayData(array $chunk, int $gameSessionId): array
    {
        $wayPointData = [];
        $eventData = [];

        foreach ($chunk as $sequence => $waypoint) {
            $wayPointData[] = CreateWayPointDTO::fromPoint($waypoint, $gameSessionId, $sequence)->toArray();

            if (!empty($waypoint['event'])) {
                $eventTypeId = $this->eventTypes[$waypoint['event']] ?? null;

                if (empty($eventTypeId)) {
                    continue; // Skip if event type is not found
                }

                $eventData[] = CreateEventDTO::fromPoint($waypoint, $gameSessionId, $sequence, $eventTypeId)->toArray();
            }
        }

        return [$wayPointData, $eventData];
    }
}
