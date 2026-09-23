<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class ZoneCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::ZONE;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        if (isset($payload['zone'])) {
            $writer->tally(StatisticBucketEnum::ZONE_NAME, (string) $payload['zone']);
        }

        if (isset($payload['sub_zone'])) {
            $writer->tally(StatisticBucketEnum::ZONE_SUB, (string) $payload['sub_zone']);
        }
    }
}
