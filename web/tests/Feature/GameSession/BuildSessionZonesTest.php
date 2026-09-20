<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildSessionZones;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildSessionZonesTest extends TestCase
{
    use RefreshDatabase;

    private const MAP_ID = 331;

    private const NEIGHBOUR_MAP_ID = 47;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert(['id' => self::MAP_ID, 'name' => 'Ashenvale']);
    }

    private function addPoint(
        GameSession $session,
        int $sequence,
        ?EventTypeEnum $type = null,
        array $payload = [],
        ?int $mapId = null,
        ?int $time = null,
    ): void {
        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => $mapId ?? self::MAP_ID,
            'time' => $time ?? $sequence,
            'x' => 0.5,
            'y' => 0.5,
        ]);

        if ($type === null) {
            return;
        }

        Event::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'event_type_id' => $type->value,
            'payload' => $payload,
        ]);
    }

    public function test_it_exposes_every_marker_type_and_hides_the_rest(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPoint($session, 2, EventTypeEnum::LEVELUP, ['level' => 10]);
        $this->addPoint($session, 3, EventTypeEnum::LOOT, ['items' => [['id' => 999, 'name' => 'Sword', 'n' => 1]]]);
        $this->addPoint($session, 4, EventTypeEnum::COMBAT, ['in_combat' => true]);
        $this->addPoint($session, 5, EventTypeEnum::QUEST, ['action' => 'accept']);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(1, $zones);

        $events = array_column($zones[0]['points'], 'event', 'sequence');

        $this->assertSame([
            1 => null,
            2 => 'levelup',
            3 => 'loot',
            4 => null,
            5 => 'quest',
        ], $events);
    }

    public function test_it_does_not_leak_payload_to_the_client(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, EventTypeEnum::LOOT, ['items' => [['id' => 999, 'name' => 'Sword', 'n' => 1]]]);

        $zones = (new BuildSessionZones)->exec($session);
        $point = $zones[0]['points'][0];

        $this->assertSame(
            ['sequence', 'time', 'x', 'y', 'state', 'gap', 'event'],
            array_keys($point),
        );
    }

    public function test_points_carry_their_time_so_the_timeline_can_play_them()
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, time: 0);
        $this->addPoint($session, 2, time: 150);
        $this->addPoint($session, 3, time: 300);

        $points = (new BuildSessionZones)->exec($session)[0]['points'];

        $this->assertSame([0, 150, 300], array_column($points, 'time'));
    }

    private function addPointWithoutMap(
        GameSession $session,
        int $sequence,
        EventTypeEnum $type,
        array $payload = [],
    ): void {
        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => null,
            'time' => $sequence,
            'x' => 0,
            'y' => 0,
        ]);

        Event::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'event_type_id' => $type->value,
            'payload' => $payload,
        ]);
    }

    public function test_a_point_without_a_map_still_moves_the_state(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPointWithoutMap($session, 2, EventTypeEnum::MOUNT, ['mounted' => true]);
        $this->addPoint($session, 3);

        $points = collect((new BuildSessionZones)->exec($session)[0]['points'])
            ->keyBy('sequence');

        $this->assertFalse($points->has(2));
        $this->assertSame('mounted', $points[3]['state']);
    }

    public function test_a_loading_screen_on_a_point_that_is_not_drawn_still_closes_the_visit(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPointWithoutMap($session, 2, EventTypeEnum::GAP, ['seconds' => 3600]);
        $this->addPoint($session, 3);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(2, $zones);
        $this->assertTrue($zones[1]['points'][0]['gap']);
    }

    public function test_it_still_tracks_state(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPoint($session, 2, EventTypeEnum::MOUNT, ['mounted' => true]);
        $this->addPoint($session, 3);
        $this->addPoint($session, 4, EventTypeEnum::TAXI, ['on_taxi' => true]);
        $this->addPoint($session, 5, EventTypeEnum::TAXI, ['on_taxi' => false]);
        $this->addPoint($session, 6, EventTypeEnum::MOUNT, ['mounted' => false]);

        $points = collect((new BuildSessionZones)->exec($session)[0]['points'])
            ->keyBy('sequence');

        $this->assertSame('ground', $points[1]['state']);
        $this->assertSame('mounted', $points[2]['state']);
        $this->assertSame('mounted', $points[3]['state']);
        $this->assertSame('flying', $points[4]['state']);
        $this->assertSame('ground', $points[5]['state']);
        $this->assertSame('ground', $points[6]['state']);
    }

    public function test_only_the_opening_point_of_a_visit_breaks_the_line(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPoint($session, 2);
        $this->addPoint($session, 3);

        $points = (new BuildSessionZones)->exec($session)[0]['points'];

        $this->assertSame([true, false, false], array_column($points, 'gap'));
    }

    public function test_a_short_trip_to_a_neighbour_map_does_not_split_the_visit(): void
    {
        Map::query()->insert(['id' => self::NEIGHBOUR_MAP_ID, 'name' => 'Duskwood']);

        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::MAP_ID, 2469);
        $this->addPoint($session, 2, null, [], self::NEIGHBOUR_MAP_ID, 2493);
        $this->addPoint($session, 3, null, [], self::MAP_ID, 2565);

        $zones = collect((new BuildSessionZones)->exec($session))
            ->where('id', self::MAP_ID);

        $this->assertCount(1, $zones, 'дребезг границы не должен плодить посещения');

        $points = $zones->first()['points'];

        $this->assertCount(2, $points);
        $this->assertFalse($points[1]['gap'], 'возврат через 9.6 с не должен рвать линию');
    }

    public function test_a_return_after_a_long_absence_is_a_separate_visit(): void
    {
        Map::query()->insert(['id' => self::NEIGHBOUR_MAP_ID, 'name' => 'Duskwood']);

        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::MAP_ID, 100);
        $this->addPoint($session, 2, null, [], self::NEIGHBOUR_MAP_ID, 200);
        $this->addPoint($session, 3, null, [], self::MAP_ID, 1500);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertSame(
            [self::MAP_ID, self::NEIGHBOUR_MAP_ID, self::MAP_ID],
            array_column($zones, 'id'),
            'посещения идут в хронологическом порядке',
        );

        $this->assertNotSame($zones[0]['key'], $zones[2]['key']);
        $this->assertCount(1, $zones[2]['points']);
        $this->assertTrue($zones[2]['points'][0]['gap']);
    }

    public function test_standing_still_on_the_same_map_keeps_one_visit(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::MAP_ID, 100);
        $this->addPoint($session, 2, null, [], self::MAP_ID, 19000);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(1, $zones, 'простой на месте не должен резать посещение');
        $this->assertFalse($zones[0]['points'][1]['gap']);
    }

    public function test_a_loading_screen_closes_the_visit(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::MAP_ID, 100);
        $this->addPoint($session, 2, EventTypeEnum::GAP, [], self::MAP_ID, 19000);
        $this->addPoint($session, 3, null, [], self::MAP_ID, 19100);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(2, $zones);
        $this->assertCount(1, $zones[0]['points']);
        $this->assertCount(2, $zones[1]['points']);
        $this->assertTrue($zones[1]['points'][0]['gap']);
    }

    public function test_it_reports_when_and_how_long_each_visit_lasted(): void
    {
        $session = GameSession::factory()->create(['session_start_at' => '2026-09-06 17:00:00']);

        $this->addPoint($session, 1, null, [], self::MAP_ID, 600);
        $this->addPoint($session, 2, null, [], self::MAP_ID, 2400);

        $zone = (new BuildSessionZones)->exec($session)[0];

        $this->assertSame('2026-09-06T17:01:00+00:00', $zone['time']);
        $this->assertSame(180, $zone['duration']);
    }

    public function test_maps_without_an_image_do_not_split_a_visit(): void
    {
        Map::query()->insert(['id' => self::NEIGHBOUR_MAP_ID, 'name' => 'Nowhere Land', 'auto_added' => false]);

        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::MAP_ID, 100);
        $this->addPoint($session, 2, null, [], self::NEIGHBOUR_MAP_ID, 20000);
        $this->addPoint($session, 3, null, [], self::MAP_ID, 40000);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(1, $zones);
        $this->assertCount(2, $zones[0]['points']);
    }

    public function test_a_map_with_an_image_exposes_its_path(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertSame('/maps/Ashenvale.png', $zones[0]['image_path']);
    }

    public function test_an_auto_added_map_is_drawn_without_an_image(): void
    {
        Map::query()->insert(['id' => self::NEIGHBOUR_MAP_ID, 'name' => 'Zone 47', 'auto_added' => true]);

        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, null, [], self::NEIGHBOUR_MAP_ID, 100);
        $this->addPoint($session, 2, null, [], self::NEIGHBOUR_MAP_ID, 200);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(1, $zones);
        $this->assertSame('Zone 47', $zones[0]['name']);
        $this->assertNull($zones[0]['image_path']);
        $this->assertCount(2, $zones[0]['points']);
    }
}
