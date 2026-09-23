<?php

namespace Tests\Feature\Statistic;

use App\Actions\Statistic\CollectSessionStatistics;
use App\Actions\Statistic\Rebuild\RebuildUserStatistics;
use App\Actions\Statistic\StoreSessionStatistics;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\UserStatistic;
use App\Support\Statistic\PointSource\ImportedPointSource;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RebuildUserStatisticsTest extends TestCase
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
     * @param  array<int, array<string, mixed>>  $points
     */
    private function addSession(User $user, string $startedAt, array $points): void
    {
        $session = GameSession::factory()->forUser($user)->create(['session_start_at' => $startedAt]);

        app(StoreSessionStatistics::class)->exec(
            $session,
            app(CollectSessionStatistics::class)->exec(new ImportedPointSource($points)),
        );
    }

    private function rebuild(User $user): UserStatistic
    {
        return app(RebuildUserStatistics::class)->exec($user);
    }

    public function test_it_sums_the_whole_account_into_one_row(): void
    {
        $user = User::factory()->create();

        $this->addSession($user, '2026-09-01 10:00:00', [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 600, 'mapId' => self::NAGRAND, 'event' => 'death',
                'killer' => ['name' => 'Hogger', 'creatureType' => 'Humanoid']],
        ]);

        $this->addSession($user, '2026-09-03 10:00:00', [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::SHATTRATH],
            ['x' => 0.5, 'y' => 0.5, 't' => 300, 'mapId' => self::SHATTRATH, 'event' => 'death',
                'killer' => ['name' => 'Hogger', 'creatureType' => 'Humanoid']],
            ['x' => 0.5, 'y' => 0.5, 't' => 360, 'mapId' => self::SHATTRATH, 'event' => 'levelup', 'level' => 62],
        ]);

        $statistics = $this->rebuild($user);

        $this->assertSame(2, $statistics->sessions_count);
        $this->assertSame(5, $statistics->points_count);
        $this->assertSame(3, $statistics->events_count);
        $this->assertSame(2, $statistics->zones_count);
        $this->assertSame(960, $statistics->seconds_played);
        $this->assertFalse($statistics->is_stale);

        $death = collect($statistics->groups)->firstWhere('event', 'death');

        $this->assertSame(2, $death['total']);
        $this->assertSame([[
            'key' => 'Hogger',
            'meta' => ['creature_type' => 'Humanoid'],
            'counters' => ['count' => 2],
        ]], $death['tables'][0]['rows']);
    }

    public function test_the_journey_is_built_from_the_facts(): void
    {
        $user = User::factory()->create();

        $this->addSession($user, '2026-09-01 10:00:00', [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 600, 'mapId' => self::NAGRAND, 'event' => 'death'],
            ['x' => 0.5, 'y' => 0.5, 't' => 660, 'mapId' => self::NAGRAND, 'event' => 'levelup', 'level' => 62],
        ]);

        $this->addSession($user, '2026-09-03 10:00:00', [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::SHATTRATH],
            ['x' => 0.5, 'y' => 0.5, 't' => 300, 'mapId' => self::SHATTRATH],
        ]);

        $journey = $this->rebuild($user)->journey;

        $this->assertSame(['2026-09-01', '2026-09-02', '2026-09-03'], array_column($journey['activity'], 'date'));
        $this->assertSame([660, 0, 300], array_column($journey['activity'], 'seconds'));

        $this->assertSame(self::NAGRAND, $journey['zones'][0]['map_id']);
        $this->assertSame(660, $journey['zones'][0]['seconds']);

        $this->assertSame([['level' => 62, 'date' => '2026-09-01']], $journey['levels']);
        $this->assertSame(['map_id' => self::NAGRAND, 'deaths' => 1], $journey['deadliest']);
    }

    public function test_another_players_facts_stay_out(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->addSession($stranger, '2026-09-01 10:00:00', [
            ['x' => 0.5, 'y' => 0.5, 't' => 0, 'mapId' => self::NAGRAND],
            ['x' => 0.5, 'y' => 0.5, 't' => 600, 'mapId' => self::NAGRAND, 'event' => 'death'],
        ]);

        $statistics = $this->rebuild($user);

        $this->assertSame(0, $statistics->sessions_count);
        $this->assertSame([], $statistics->groups);
        $this->assertSame([], $statistics->journey['activity']);
        $this->assertNull($statistics->journey['deadliest']);
    }
}
