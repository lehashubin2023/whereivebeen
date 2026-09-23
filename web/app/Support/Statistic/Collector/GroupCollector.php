<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use App\Support\Statistic\StatisticWriter;

class GroupCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::GROUP;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        foreach ([StatisticCounterEnum::JOINED, StatisticCounterEnum::LEFT] as $counter) {
            $members = $payload[$counter->slug()] ?? null;

            if (! is_array($members)) {
                continue;
            }

            foreach ($members as $member) {
                $writer->tally(StatisticBucketEnum::GROUP_MEMBER, (string) $member, $counter);
            }
        }
    }
}
