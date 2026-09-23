<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use App\Support\Statistic\StatisticWriter;

class QuestCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::QUEST;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $turnedIn = ($payload['action'] ?? null) === 'turnin';
        $counter = $turnedIn ? StatisticCounterEnum::TURNIN : StatisticCounterEnum::ACCEPT;

        $writer->tally(StatisticBucketEnum::QUEST_ACTION, $counter->slug());

        $title = (string) ($payload['title'] ?? '');

        if ($title === '' && isset($payload['quest_id'])) {
            $title = '#'.$payload['quest_id'];
        }

        $writer->tally(StatisticBucketEnum::QUEST_TITLE, $title, $counter);
    }
}
