<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildUserJourney;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildUserJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const NAGRAND = 200;

    private const SHATTRATH = 201;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert([
            ['id' => self::NAGRAND, 'name' => 'Nagrand'],
            ['id' => self::SHATTRATH, 'name' => 'Shattrath City'],
        ]);
    }

    public function test_an_empty_account_gets_an_empty_journey(): void
    {
        $journey = app(BuildUserJourney::class)->exec(User::factory()->create());

        $this->assertSame([], $journey['activity']);
        $this->assertSame([], $journey['zones']);
        $this->assertSame([], $journey['levels']);
        $this->assertNull($journey['deadliest']);
    }

    public function test_activity_fills_the_days_between_sessions(): void
    {
        $user = User::factory()->create();

        $first = GameSession::factory()->forUser($user)->create([
            'session_start_at' => '2026-09-01 10:00:00',
        ]);
        $second = GameSession::factory()->forUser($user)->create([
            'session_start_at' => '2026-09-03 10:00:00',
        ]);

        $this->addPoint($first, 1, self::NAGRAND, 0);
        $this->addPoint($first, 2, self::NAGRAND, 6000);
        $this->addPoint($second, 1, self::NAGRAND, 0);
        $this->addPoint($second, 2, self::NAGRAND, 3000);

        $activity = app(BuildUserJourney::class)->exec($user)['activity'];

        $this->assertSame(
            ['2026-09-01', '2026-09-02', '2026-09-03'],
            array_column($activity, 'date'),
        );
        $this->assertSame([600, 0, 300], array_column($activity, 'seconds'));
    }

    public function test_zone_time_ignores_long_jumps_between_points(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::NAGRAND, 0);
        $this->addPoint($session, 2, self::NAGRAND, 1500);
        $this->addPoint($session, 3, self::NAGRAND, 37500);
        $this->addPoint($session, 4, self::SHATTRATH, 38100);

        $zones = collect(app(BuildUserJourney::class)->exec($user)['zones'])
            ->keyBy('name');

        $this->assertSame(150, $zones['Nagrand']['seconds']);
        $this->assertSame(3, $zones['Nagrand']['points']);
        $this->assertSame(0, $zones['Shattrath City']['seconds']);
    }

    public function test_levels_are_dated_by_the_session_that_reached_them(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create([
            'session_start_at' => '2026-09-05 12:00:00',
        ]);

        $this->addPoint($session, 1, self::NAGRAND, 0);
        $this->addEvent($session, 1, EventTypeEnum::LEVELUP, ['level' => 62]);
        $this->addEvent($session, 2, EventTypeEnum::LEVELUP, ['level' => 61]);

        $levels = app(BuildUserJourney::class)->exec($user)['levels'];

        $this->assertSame([61, 62], array_column($levels, 'level'));
        $this->assertSame('2026-09-05', $levels[0]['date']);
    }

    public function test_the_deadliest_zone_is_the_one_with_most_deaths(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::NAGRAND, 0);
        $this->addPoint($session, 2, self::NAGRAND, 100);
        $this->addPoint($session, 3, self::SHATTRATH, 200);

        $this->addEvent($session, 1, EventTypeEnum::DEATH, []);
        $this->addEvent($session, 2, EventTypeEnum::DEATH, []);
        $this->addEvent($session, 3, EventTypeEnum::DEATH, []);

        $deadliest = app(BuildUserJourney::class)->exec($user)['deadliest'];

        $this->assertSame('Nagrand', $deadliest['name']);
        $this->assertSame(2, $deadliest['deaths']);
    }

    public function test_another_players_data_stays_out(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $session = GameSession::factory()->forUser($stranger)->create();
        $this->addPoint($session, 1, self::NAGRAND, 0);

        $this->assertSame([], app(BuildUserJourney::class)->exec($user)['zones']);
    }

    private function addPoint(GameSession $session, int $sequence, int $mapId, int $time): void
    {
        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => $mapId,
            'time' => $time,
            'x' => 0.5,
            'y' => 0.5,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function addEvent(GameSession $session, int $sequence, EventTypeEnum $type, array $payload): void
    {
        Event::query()->insert([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'event_type_id' => $type->value,
            'payload' => json_encode($payload),
        ]);
    }
}
