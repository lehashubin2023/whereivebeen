<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateEventDTO;
use App\DTOs\GameSession\CreateWayPointDTO;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\WayPoint;
use Illuminate\Support\Facades\DB;

class CreateWay
{
    const CHUNK_SIZE = 1000;

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

            if (!empty($waypoint['event_type_id'])) {
                $eventData[] = CreateEventDTO::fromPoint($waypoint, $gameSessionId, $sequence)->toArray();
            }
        }

        return [$wayPointData, $eventData];
    }
}
