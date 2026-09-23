<?php

namespace Tests\Feature\Statistic;

use App\Actions\GameSession\CreateWay;
use App\Actions\Statistic\CollectSessionStatistics;
use App\DTOs\Statistic\SessionStatisticsDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Support\Statistic\PointSource\ImportedPointSource;
use App\Support\Statistic\PointSource\StoredPointSource;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectSessionStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const NAGRAND = 200;

    private const SHATTRATH = 201;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert([
            ['id' => self::NAGRAND, 'name' => 'Nagrand'],
            ['id' => self::SHATTRATH, 'name' => 'Shattrath City'],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function points(): array
    {
        return [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 150, 'mapId' => self::NAGRAND, 'event' => 'death',
                'killer' => ['name' => 'Hogger', 'creatureType' => 'Humanoid']],
            ['x' => 0.5, 'y' => 0.5, 't' => 3750, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 3810, 'mapId' => self::SHATTRATH, 'event' => 'loot',
                'items' => [['id' => 858, 'name' => 'Linen Cloth', 'n' => 4]]],
            ['x' => 0.5, 'y' => 0.5, 't' => 3900, 'mapId' => self::SHATTRATH, 'event' => 'gap',
                'reason' => 'logout', 'seconds' => 60],
        ];
    }

    private function collect(): SessionStatisticsDTO
    {
        return app(CollectSessionStatistics::class)->exec(new ImportedPointSource($this->points()));
    }

    public function test_it_measures_duration_zones_and_deaths(): void
    {
        $statistics = $this->collect();

        $this->assertSame(840, $statistics->durationSeconds);
        $this->assertSame(5, $statistics->pointsCount);

        $this->assertSame([
            EventTypeEnum::DEATH->value => 1,
            EventTypeEnum::LOOT->value => 1,
            EventTypeEnum::GAP->value => 1,
        ], $statistics->eventCounts);

        $this->assertSame([
            self::NAGRAND => ['seconds' => 150, 'points' => 3, 'deaths' => 1],
            self::SHATTRATH => ['seconds' => 90, 'points' => 2, 'deaths' => 0],
        ], $statistics->maps);
    }

    public function test_a_gap_of_unknown_length_is_not_counted_as_play_time(): void
    {
        $statistics = app(CollectSessionStatistics::class)->exec(new ImportedPointSource([
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 3600, 'mapId' => self::NAGRAND, 'event' => 'gap', 'reason' => 'loading'],
        ]));

        $this->assertSame(0, $statistics->durationSeconds);
    }

    public function test_it_keeps_entry_keys_locale_neutral(): void
    {
        $entries = array_map(
            fn ($entry) => $entry->toArray(),
            $this->collect()->entries,
        );

        $this->assertContains([
            'bucket' => StatisticBucketEnum::DEATH_KILLER->value,
            'counter' => StatisticCounterEnum::COUNT->value,
            'entry_key' => 'Hogger',
            'value' => 1,
            'meta' => '{"creature_type":"Humanoid"}',
        ], $entries);

        $this->assertContains([
            'bucket' => StatisticBucketEnum::LOOT_ITEM->value,
            'counter' => StatisticCounterEnum::QUANTITY->value,
            'entry_key' => 'Linen Cloth',
            'value' => 4,
            'meta' => null,
        ], $entries);

        $this->assertContains([
            'bucket' => StatisticBucketEnum::GAP_REASON->value,
            'counter' => StatisticCounterEnum::SECONDS->value,
            'entry_key' => 'logout',
            'value' => 60,
            'meta' => null,
        ], $entries);
    }

    public function test_imported_and_stored_points_produce_the_same_statistics(): void
    {
        $session = GameSession::factory()->forUser(User::factory()->create())->create();

        app(CreateWay::class)->exec($session, $this->points());

        $stored = app(CollectSessionStatistics::class)->exec(new StoredPointSource($session->id));

        $this->assertEquals($this->collect()->toArray(), $stored->toArray());
    }
}
