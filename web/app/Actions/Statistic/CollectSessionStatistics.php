<?php

namespace App\Actions\Statistic;

use App\DTOs\Statistic\SessionStatisticsDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Support\Statistic\Collector\CollectorRegistry;
use App\Support\Statistic\PointSource\PointSourceContract;
use App\Support\Statistic\StatisticPoint;
use App\Support\Statistic\StatisticWriter;

class CollectSessionStatistics
{
    private const STEP_LIMIT = 600;

    private const GAP_OF_UNKNOWN_LENGTH = -1;

    public function __construct(private readonly CollectorRegistry $collectors) {}

    public function exec(PointSourceContract $source): SessionStatisticsDTO
    {
        $writer = new StatisticWriter;
        $eventCounts = [];
        $maps = [];
        $duration = 0;
        $points = 0;
        $previousTime = null;
        $previousMapId = null;
        $previousMapTime = 0;

        foreach ($source->points() as $point) {
            $points++;

            if ($previousTime !== null) {
                $duration += $this->step($point, $previousTime);
            }

            $previousTime = $point->time;

            if ($point->mapId !== null) {
                $maps[$point->mapId] ??= ['seconds' => 0, 'points' => 0, 'deaths' => 0];
                $maps[$point->mapId]['points']++;

                if ($previousMapId === $point->mapId) {
                    $step = (int) round(($point->time - $previousMapTime) / 10);

                    if ($step > 0 && $step <= self::STEP_LIMIT) {
                        $maps[$point->mapId]['seconds'] += $step;
                    }
                }

                $previousMapId = $point->mapId;
                $previousMapTime = $point->time;
            }

            if ($point->event === null) {
                continue;
            }

            $eventCounts[$point->event->value] = ($eventCounts[$point->event->value] ?? 0) + 1;

            if ($point->event === EventTypeEnum::DEATH && $point->mapId !== null) {
                $maps[$point->mapId]['deaths']++;
            }

            $this->collectors->for($point->event)?->collect($point->payload, $writer);
        }

        return new SessionStatisticsDTO(
            durationSeconds: $duration,
            pointsCount: $points,
            eventCounts: $eventCounts,
            maps: $maps,
            entries: $writer->entries(),
        );
    }

    private function step(StatisticPoint $point, int $previousTime): int
    {
        $offline = $this->offlineSeconds($point);

        $step = $offline === self::GAP_OF_UNKNOWN_LENGTH
            ? 0
            : (int) round(($point->time - $previousTime) / 10) - $offline;

        return max(0, min($step, self::STEP_LIMIT));
    }

    private function offlineSeconds(StatisticPoint $point): int
    {
        if ($point->event !== EventTypeEnum::GAP) {
            return 0;
        }

        return isset($point->payload['seconds'])
            ? (int) $point->payload['seconds']
            : self::GAP_OF_UNKNOWN_LENGTH;
    }
}
