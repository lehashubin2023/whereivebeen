<?php

namespace App\Support\Statistic\PointSource;

use App\Enums\GameSession\EventTypeEnum;
use App\Support\GameSession\EventPayload;
use App\Support\GameSession\MapId;
use App\Support\Statistic\StatisticPoint;

class ImportedPointSource implements PointSourceContract
{
    /**
     * @param  array<int, array<string, mixed>>  $points
     */
    public function __construct(private readonly array $points) {}

    public function points(): iterable
    {
        foreach ($this->points as $point) {
            $slug = $point['event'] ?? null;
            $event = is_string($slug) ? EventTypeEnum::fromSlug($slug) : null;

            yield new StatisticPoint(
                time: (int) round(((float) ($point['t'] ?? 0)) * 10),
                mapId: MapId::sanitize($point['mapId'] ?? null),
                event: $event,
                payload: $event === null ? [] : EventPayload::fromPoint($point),
            );
        }
    }
}
