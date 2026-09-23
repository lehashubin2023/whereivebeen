<?php

namespace App\Support\Statistic;

use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;

class TableDefinition
{
    /**
     * @param  array<int, TableColumn>  $columns
     */
    public function __construct(
        public readonly StatisticBucketEnum $bucket,
        public readonly string $title,
        public readonly string $keyColumn,
        public readonly array $columns,
        public readonly ?string $metaColumn = null,
        public readonly ?StatisticCounterEnum $sortCounter = null,
        public readonly bool $naturalKeySort = false,
    ) {}
}
