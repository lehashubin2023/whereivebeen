<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Support\Statistic\StatisticWriter;

interface StatisticCollectorContract
{
    public function eventType(): EventTypeEnum;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function collect(array $payload, StatisticWriter $writer): void;
}
