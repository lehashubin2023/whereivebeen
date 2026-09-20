<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateEventDTO;
use App\DTOs\GameSession\CreateWayPointDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\WayPoint;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgressContract;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
use App\Support\GameSession\MapId;
use Illuminate\Support\Facades\DB;

class CreateWay
{
    const CHUNK_SIZE = 1000;

    /**
     * @param  array<int, array<string, mixed>>  $waypoints
     */
    public function exec(
        GameSession $gameSession,
        array $waypoints,
        ImportGameSessionProgressContract $progress = new NullImportGameSessionProgress
    ): void {
        $sequence = 1;

        foreach (array_chunk($waypoints, $this->getChunkSize()) as $chunk) {
            [$wayPointData, $eventData] = $this->prepareWayData($chunk, $gameSession->id, $sequence);

            DB::transaction(function () use ($wayPointData, $eventData) {
                WayPoint::insert(array_map(
                    fn (array $row) => (new WayPoint($row))->getAttributes(),
                    $wayPointData
                ));
                Event::insert($eventData);
            });

            $progress->track($sequence - 1);
        }
    }

    private function prepareWayData(array $chunk, int $gameSessionId, int &$sequence): array
    {
        $wayPointData = [];
        $eventData = [];

        foreach ($chunk as $waypoint) {
            $waypoint['mapId'] = MapId::sanitize($waypoint['mapId'] ?? null);

            $wayPointData[] = CreateWayPointDTO::fromPoint($waypoint, $gameSessionId, $sequence)->toArray();

            if (! empty($waypoint['event'])) {
                $eventType = EventTypeEnum::fromSlug($waypoint['event']);

                if ($eventType !== null) {
                    $eventData[] = CreateEventDTO::fromPoint($waypoint, $gameSessionId, $sequence, $eventType->value)->toArray();
                }
            }

            $sequence += 1;
        }

        return [$wayPointData, $eventData];
    }

    public function getChunkSize()
    {
        return self::CHUNK_SIZE;
    }
}
