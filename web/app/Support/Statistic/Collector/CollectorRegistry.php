<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;

class CollectorRegistry
{
    /** @var array<int, StatisticCollectorContract>|null */
    private ?array $collectors = null;

    public function for(EventTypeEnum $type): ?StatisticCollectorContract
    {
        return $this->collectors()[$type->value] ?? null;
    }

    /**
     * @return array<int, StatisticCollectorContract>
     */
    private function collectors(): array
    {
        if ($this->collectors !== null) {
            return $this->collectors;
        }

        $collectors = [];

        foreach ([
            new DeathCollector,
            new QuestCollector,
            new LootCollector,
            new GatherCollector,
            new VisitCollector,
            new GroupCollector,
            new ZoneCollector,
            new LevelUpCollector,
            new GapCollector,
            StateFlagCollector::mount(),
            StateFlagCollector::taxi(),
            StateFlagCollector::combat(),
        ] as $collector) {
            $collectors[$collector->eventType()->value] = $collector;
        }

        return $this->collectors = $collectors;
    }
}
