<?php

namespace App\Support\Statistic;

use App\Enums\GameSession\EventTypeEnum;

class StatisticPoint
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly int $time,
        public readonly ?int $mapId,
        public readonly ?EventTypeEnum $event,
        public readonly array $payload = [],
    ) {}
}
