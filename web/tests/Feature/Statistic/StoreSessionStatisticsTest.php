<?php

namespace Tests\Feature\Statistic;

use App\Actions\Statistic\CollectSessionStatistics;
use App\Actions\Statistic\StoreSessionStatistics;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\SessionEventCount;
use App\Models\SessionMapStat;
use App\Models\SessionStatisticEntry;
use App\Models\User;
use App\Support\Statistic\PointSource\ImportedPointSource;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSessionStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const NAGRAND = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert(['id' => self::NAGRAND, 'name' => 'Nagrand']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     */
    private function store(GameSession $session, array $points): void
    {
        app(StoreSessionStatistics::class)->exec(
            $session,
            app(CollectSessionStatistics::class)->exec(new ImportedPointSource($points)),
        );
    }

    public function test_it_replaces_the_facts_of_a_reimported_session(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->store($session, [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 60, 'mapId' => self::NAGRAND, 'event' => 'death',
                'killer' => ['name' => 'Hogger']],
        ]);

        $this->assertSame(60, $session->fresh()->duration_seconds);
        $this->assertSame(2, $session->fresh()->points_count);
        $this->assertDatabaseHas('session_statistic_entries', [
            'user_id' => $user->id,
            'bucket' => StatisticBucketEnum::DEATH_KILLER->value,
            'entry_key' => 'Hogger',
            'value' => 1,
        ]);

        $this->store($session, [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 30, 'mapId' => self::NAGRAND, 'event' => 'death',
                'killer' => ['name' => 'Murloc']],
        ]);

        $this->assertSame(30, $session->fresh()->duration_seconds);
        $this->assertDatabaseMissing('session_statistic_entries', ['entry_key' => 'Hogger']);
        $this->assertSame(1, SessionStatisticEntry::query()->count());
        $this->assertSame(1, SessionEventCount::query()->count());
        $this->assertSame(1, SessionMapStat::query()->count());
    }
}
