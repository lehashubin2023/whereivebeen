<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class VisitCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::VISIT;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $places = $payload['places'] ?? null;

        if (is_array($places)) {
            foreach ($places as $place) {
                $writer->tally(StatisticBucketEnum::VISIT_PLACE, strtolower((string) $place));
            }
        }

        if (isset($payload['npc_name'])) {
            $writer->tally(StatisticBucketEnum::VISIT_NPC, (string) $payload['npc_name']);
        }
    }
}
