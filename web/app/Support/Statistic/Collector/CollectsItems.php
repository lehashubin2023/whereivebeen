<?php

namespace App\Support\Statistic\Collector;

use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use App\Support\Statistic\StatisticWriter;

trait CollectsItems
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function collectItems(array $payload, StatisticBucketEnum $bucket, StatisticWriter $writer): void
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? $item['id'] ?? '');

            $writer->tally($bucket, $name, StatisticCounterEnum::QUANTITY, max(1, (int) ($item['n'] ?? 1)));
            $writer->tally($bucket, $name, StatisticCounterEnum::DROPS);
        }
    }
}
