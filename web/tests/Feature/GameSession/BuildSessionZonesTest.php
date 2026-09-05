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
    ): void {
        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => self::MAP_ID,
            'time' => $sequence,
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
        $this->addPoint($session, 3, EventTypeEnum::LOOT, ['item_name' => 'Sword']);
        $this->addPoint($session, 4, EventTypeEnum::COMBAT, ['in_combat' => true]);
        $this->addPoint($session, 5, EventTypeEnum::GAP);
        $this->addPoint($session, 6, EventTypeEnum::QUEST, ['action' => 'accept']);

        $zones = (new BuildSessionZones)->exec($session);

        $this->assertCount(1, $zones);

        $events = array_column($zones[0]['points'], 'event', 'sequence');

        $this->assertSame([
            1 => null,
            2 => 'levelup',
            3 => 'loot',
            4 => null,
            5 => null,
            6 => 'quest',
        ], $events);
    }

    public function test_it_does_not_leak_payload_to_the_client(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1, EventTypeEnum::LOOT, ['item_name' => 'Sword']);

        $zones = (new BuildSessionZones)->exec($session);
        $point = $zones[0]['points'][0];

        $this->assertSame(
            ['sequence', 'x', 'y', 'state', 'gap', 'event'],
            array_keys($point),
        );
    }

    public function test_it_still_tracks_state_and_gaps(): void
    {
        $session = GameSession::factory()->create();

        $this->addPoint($session, 1);
        $this->addPoint($session, 2, EventTypeEnum::MOUNT, ['mounted' => true]);
        $this->addPoint($session, 3);
        $this->addPoint($session, 4, EventTypeEnum::TAXI, ['on_taxi' => true]);
        $this->addPoint($session, 5, EventTypeEnum::GAP);
        $this->addPoint($session, 6, EventTypeEnum::TAXI, ['on_taxi' => false]);
        $this->addPoint($session, 7, EventTypeEnum::MOUNT, ['mounted' => false]);

        $points = collect((new BuildSessionZones)->exec($session)[0]['points'])
            ->keyBy('sequence');

        $this->assertSame('ground', $points[1]['state']);
        $this->assertSame('mounted', $points[2]['state']);
        $this->assertSame('mounted', $points[3]['state']);
        $this->assertSame('flying', $points[4]['state']);
        $this->assertSame('ground', $points[6]['state']);
        $this->assertSame('ground', $points[7]['state']);

        $this->assertTrue($points[1]['gap']);
        $this->assertFalse($points[2]['gap']);
        $this->assertTrue($points[5]['gap']);
    }
}
