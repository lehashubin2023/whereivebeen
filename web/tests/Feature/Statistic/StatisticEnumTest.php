<?php

namespace Tests\Feature\Statistic;

use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use Tests\TestCase;

class StatisticEnumTest extends TestCase
{
    public function test_bucket_slugs_round_trip_and_stay_unique(): void
    {
        $slugs = [];

        foreach (StatisticBucketEnum::cases() as $case) {
            $this->assertSame($case, StatisticBucketEnum::fromSlug($case->slug()));

            $slugs[] = $case->slug();
        }

        $this->assertSame($slugs, array_unique($slugs));
    }

    public function test_every_bucket_belongs_to_the_event_named_by_its_slug(): void
    {
        foreach (StatisticBucketEnum::cases() as $case) {
            $this->assertSame(
                strtok($case->slug(), '.'),
                $case->eventType()->slug(),
            );
        }
    }

    public function test_counter_slugs_round_trip(): void
    {
        foreach (StatisticCounterEnum::cases() as $case) {
            $this->assertSame($case, StatisticCounterEnum::fromSlug($case->slug()));
        }
    }
}
