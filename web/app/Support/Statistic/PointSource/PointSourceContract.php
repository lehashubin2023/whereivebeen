<?php

namespace App\Support\Statistic\PointSource;

use App\Support\Statistic\StatisticPoint;

interface PointSourceContract
{
    /**
     * @return iterable<int, StatisticPoint>
     */
    public function points(): iterable;
}
