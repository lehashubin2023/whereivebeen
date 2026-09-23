<?php

namespace App\Support\Statistic\Collector;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Support\Statistic\StatisticWriter;

class StateFlagCollector implements StatisticCollectorContract
{
    public function __construct(
        private readonly EventTypeEnum $eventType,
        private readonly StatisticBucketEnum $bucket,
        private readonly string $payloadKey,
        private readonly string $onKey,
        private readonly string $offKey,
    ) {}

    public static function mount(): self
    {
        return new self(EventTypeEnum::MOUNT, StatisticBucketEnum::MOUNT_STATE, 'mounted', 'mounted', 'dismounted');
    }

    public static function taxi(): self
    {
        return new self(EventTypeEnum::TAXI, StatisticBucketEnum::TAXI_STATE, 'on_taxi', 'takeoff', 'landing');
    }

    public static function combat(): self
    {
        return new self(EventTypeEnum::COMBAT, StatisticBucketEnum::COMBAT_STATE, 'in_combat', 'entered', 'left');
    }

    public function eventType(): EventTypeEnum
    {
        return $this->eventType;
    }

    public function collect(array $payload, StatisticWriter $writer): void
    {
        $writer->tally($this->bucket, ($payload[$this->payloadKey] ?? false) ? $this->onKey : $this->offKey);
    }
}
