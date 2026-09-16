<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildSessionList;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSessionListTest extends TestCase
{
    use RefreshDatabase;

    private const HELLFIRE = 100;

    private const ZANGARMARSH = 101;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert([
            ['id' => self::HELLFIRE, 'name' => 'Hellfire Peninsula'],
            ['id' => self::ZANGARMARSH, 'name' => 'Zangarmarsh'],
        ]);
    }

    public function test_a_session_carries_its_length_and_point_count(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::HELLFIRE, 0);
        $this->addPoint($session, 2, self::HELLFIRE, 600);
        $this->addPoint($session, 3, self::ZANGARMARSH, 1200);

        $row = $this->firstRow($user);

        $this->assertSame(120, $row['duration']);
        $this->assertSame(3, $row['points_count']);
    }

    public function test_time_spent_logged_out_is_not_counted_as_play_time(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::HELLFIRE, 0);
        $this->addPoint($session, 2, self::HELLFIRE, 36000);

        $this->addEvent($session, 2, EventTypeEnum::GAP, ['reason' => 'login', 'seconds' => 3000]);

        $this->assertSame(600, $this->firstRow($user)['duration']);
    }

    public function test_an_absence_the_addon_never_marked_is_not_counted_as_play_time(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::HELLFIRE, 0);
        $this->addPoint($session, 2, self::HELLFIRE, 1200);
        $this->addPoint($session, 3, self::HELLFIRE, 451200);
        $this->addPoint($session, 4, self::HELLFIRE, 452400);

        $this->assertSame(840, $this->firstRow($user)['duration']);
    }

    public function test_a_gap_without_a_length_is_not_counted_as_play_time(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addPoint($session, 1, self::HELLFIRE, 0);
        $this->addPoint($session, 2, self::HELLFIRE, 36000);

        $this->addEvent($session, 2, EventTypeEnum::GAP, ['reason' => 'loading']);

        $this->assertSame(0, $this->firstRow($user)['duration']);
    }

    public function test_levels_come_from_the_level_up_events(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create(['level' => 60]);

        $this->addPoint($session, 1, self::HELLFIRE, 0);
        $this->addPoint($session, 2, self::HELLFIRE, 600);

        $this->addLevelUp($session, 1, 61);
        $this->addLevelUp($session, 2, 62);

        $row = $this->firstRow($user);

        $this->assertSame(60, $row['level_from']);
        $this->assertSame(62, $row['level_to']);
    }

    public function test_without_level_ups_the_session_level_is_used(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create(['level' => 58]);

        $this->addPoint($session, 1, self::HELLFIRE, 0);

        $row = $this->firstRow($user);

        $this->assertSame(58, $row['level_from']);
        $this->assertSame(58, $row['level_to']);
    }

    public function test_the_list_can_be_filtered_by_character(): void
    {
        $user = User::factory()->create();

        $one = GameSession::factory()->forUser($user)->create(['character' => 'Thrall', 'realm' => 'Kazzak']);
        $two = GameSession::factory()->forUser($user)->create(['character' => 'Jaina', 'realm' => 'Kazzak']);

        $this->addPoint($one, 1, self::HELLFIRE, 0);
        $this->addPoint($two, 1, self::ZANGARMARSH, 0);

        $filtered = app(BuildSessionList::class)->exec($user, 'Thrall', 'Kazzak');

        $this->assertCount(1, $filtered->items());
        $this->assertSame('Thrall', $filtered->items()[0]['character']);
    }

    public function test_characters_are_listed_with_their_session_counts(): void
    {
        $user = User::factory()->create();

        GameSession::factory()->forUser($user)->count(2)->create(['character' => 'Thrall', 'realm' => 'Kazzak']);
        GameSession::factory()->forUser($user)->create(['character' => 'Jaina', 'realm' => 'Kazzak']);

        $characters = app(BuildSessionList::class)->characters($user);

        $this->assertCount(2, $characters);
        $this->assertSame(
            ['Jaina' => 1, 'Thrall' => 2],
            collect($characters)->pluck('sessions', 'character')->sortKeys()->all(),
        );
    }

    public function test_other_players_sessions_stay_out(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        GameSession::factory()->forUser($stranger)->create();

        $this->assertCount(0, app(BuildSessionList::class)->exec($user)->items());
    }

    /**
     * @return array<string, mixed>
     */
    private function firstRow(User $user): array
    {
        return app(BuildSessionList::class)->exec($user)->items()[0];
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

    private function addLevelUp(GameSession $session, int $sequence, int $level): void
    {
        $this->addEvent($session, $sequence, EventTypeEnum::LEVELUP, ['level' => $level]);
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
