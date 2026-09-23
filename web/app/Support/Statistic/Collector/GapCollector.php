<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use App\Support\Statistic\StatisticWriter;

class GapCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::GAP;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $reason = strtolower((string) ($payload['reason'] ?? 'unknown'));

        $writer->tally(StatisticBucketEnum::GAP_REASON, $reason);
        $writer->tally(
            StatisticBucketEnum::GAP_REASON,
            $reason,
            StatisticCounterEnum::SECONDS,
            max(0, (int) ($payload['seconds'] ?? 0)),
        );
    }
}
