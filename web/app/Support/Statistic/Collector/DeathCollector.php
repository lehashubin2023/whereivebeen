<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class DeathCollector implements StatisticCollectorContract
{
    public function eventType(): EventTypeEnum
    {
        return EventTypeEnum::DEATH;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        if (isset($payload['environment'])) {
            $writer->tally(StatisticBucketEnum::DEATH_CAUSE, strtolower((string) $payload['environment']));
        }

        $killer = (array) ($payload['killer'] ?? []);
        $name = (string) ($killer['name'] ?? '');

        if ($name !== '') {
            $writer->tally(StatisticBucketEnum::DEATH_KILLER, $name);
            $writer->describe(StatisticBucketEnum::DEATH_KILLER, $name, $this->kind($killer));
        }

        if (isset($killer['spell'])) {
            $writer->tally(StatisticBucketEnum::DEATH_SPELL, (string) $killer['spell']);
        }
    }

    /**
     * @param  array<string, mixed>  $killer
     * @return array<string, mixed>
     */
    private function kind(array $killer): array
    {
        $meta = [];

        if (isset($killer['class'])) {
            $meta['class'] = (string) $killer['class'];
        }

        if (isset($killer['creatureType'])) {
            $meta['creature_type'] = (string) $killer['creatureType'];
        }

        if ($killer['pvp'] ?? false) {
            $meta['pvp'] = true;
        }

        return $meta;
    }
}
