<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateEventDTO;
use App\DTOs\GameSession\CreateWayPointDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\WayPoint;
use Illuminate\Support\Facades\DB;

class CreateWay
{
    const CHUNK_SIZE = 1000;

    private int $sequence = 1;

    public function exec(GameSession $gameSession, array $waypoints): void
    {
        foreach (array_chunk($waypoints, $this->getChunkSize()) as $chunk) {
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

        foreach ($chunk as $waypoint) {
            $wayPointData[] = CreateWayPointDTO::fromPoint($waypoint, $gameSessionId, $this->sequence)->toArray();

            if (!empty($waypoint['event'])) {
                $eventType = EventTypeEnum::fromSlug($waypoint['event']);

                if ($eventType !== null) {
                    $eventData[] = CreateEventDTO::fromPoint($waypoint, $gameSessionId, $this->sequence, $eventType->value)->toArray();
                }
            }

            $this->sequence += 1;
        }

        return [$wayPointData, $eventData];
    }

    public function getChunkSize()
    {
        return self::CHUNK_SIZE;
    }
}
