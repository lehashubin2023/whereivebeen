<?php

namespace App\Support\Statistic;

use App\Enums\Statistic\StatisticCounterEnum;

class TableColumn
{
    public function __construct(
        public readonly StatisticCounterEnum $counter,
        public readonly string $label,
        public readonly bool $duration = false,
    ) {}
}
