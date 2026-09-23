<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class LevelUpCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::LEVELUP;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        if (isset($payload['level'])) {
            $writer->tally(StatisticBucketEnum::LEVELUP_LEVEL, (string) (int) $payload['level']);
        }
    }
}
