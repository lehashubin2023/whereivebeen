<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class LootCollector implements StatisticCollectorContract
{
    use CollectsItems;

    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::LOOT;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $this->collectItems($payload, StatisticBucketEnum::LOOT_ITEM, $writer);
    }
}
