<?php

namespace App\Support\Statistic;

use App\DTOs\Statistic\StatisticEntryDTO;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;

class StatisticWriter
{
    /** @var array<int, array<string, array<int, int>>> */
    private array $counters = [];

    /** @var array<int, array<string, array<string, mixed>>> */
    private array $meta = [];

    public function tally(
        StatisticBucketEnum $bucket,
        string $key,
        StatisticCounterEnum $counter = StatisticCounterEnum::COUNT,
        int $by = 1,
    ): void {
        $key = self::normalize($key);

        if ($key === '') {
            return;
        }

        $this->counters[$bucket->value][$key][$counter->value]
            = ($this->counters[$bucket->value][$key][$counter->value] ?? 0) + $by;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function describe(StatisticBucketEnum $bucket, string $key, array $meta): void
    {
        $key = self::normalize($key);

        if ($key === '') {
            return;
        }

        $this->meta[$bucket->value][$key] ??= $meta;
    }

    /**
     * @return array<int, StatisticEntryDTO>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ($this->counters as $bucket => $keys) {
            foreach ($keys as $key => $counters) {
                foreach ($counters as $counter => $value) {
                    $entries[] = StatisticEntryDTO::fromArray([
                        'bucket' => $bucket,
                        'counter' => $counter,
                        'entry_key' => (string) $key,
                        'value' => $value,
                        'meta' => $this->meta[$bucket][$key] ?? null,
                    ]);
                }
            }
        }

        return $entries;
    }

    private static function normalize(string $key): string
    {
        return mb_substr(trim($key), 0, StatisticBucketEnum::KEY_LENGTH);
    }
}
