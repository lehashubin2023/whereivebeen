<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class GatherCollector implements StatisticCollectorContract
{
    use CollectsItems;

    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::GATHER;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $node = (array) ($payload['node'] ?? []);

        if (isset($node['name'])) {
            $writer->tally(StatisticBucketEnum::GATHER_NODE, (string) $node['name']);
        }

        if (isset($node['prof'])) {
            $writer->tally(StatisticBucketEnum::GATHER_PROF, strtolower((string) $node['prof']));
        }

        $this->collectItems($payload, StatisticBucketEnum::GATHER_ITEM, $writer);
    }
}
