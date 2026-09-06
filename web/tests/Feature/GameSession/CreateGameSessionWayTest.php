<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\CreateWay;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateGameSessionWayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);
    }

    public function test_way_session_created_correctly()
    {
        $mock = $this->partialMock(CreateWay::class, fn ($mock) => $mock->shouldReceive('getChunkSize')->andReturn(2));
        $session = GameSession::factory()->create();
        $wayPointTable = (new WayPoint)->getTable();
        $eventTable = (new Event)->getTable();

        $mock->exec($session, [
            ['x' => 0.10, 'y' => 0.20, 'mapId' => null, 't' => 0],
            ['x' => 0.11, 'y' => 0.21, 'mapId' => null, 'event' => 'combat', 'inCombat' => true, 't' => 0],
            ['x' => 0.12, 'y' => 0.22, 'mapId' => null, 'event' => 'loot', 'items' => [['id' => 999, 'name' => 'Sword', 'n' => 2]], 't' => 0],
            ['x' => 0.13, 'y' => 0.23, 'mapId' => null, 'event' => 'unknown', 't' => 0],
        ]);

        $scale = WayPoint::COODS_FIELD_LENGTH;

        $this->assertDatabaseCount($wayPointTable, 4);
        $this->assertDatabaseCount($eventTable, 2);

        $this->assertDatabaseHas($wayPointTable, [
            'game_session_id' => $session->id,
            'sequence' => 2,
            'x' => (int) (0.11 * $scale),
            'y' => (int) (0.21 * $scale),
        ]);
        $this->assertDatabaseHas($wayPointTable, [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'x' => (int) (0.13 * $scale),
            'y' => (int) (0.23 * $scale),
        ]);

        $this->assertDatabaseHas($eventTable, [
            'game_session_id' => $session->id,
            'sequence' => 3,
            'event_type_id' => EventTypeEnum::LOOT,
            'payload' => $this->castAsJson(['items' => [['id' => 999, 'name' => 'Sword', 'n' => 2]]]),
        ]);
        $this->assertDatabaseMissing($eventTable, [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'event_type_id' => null,
            'payload' => json_encode([]),
        ]);
    }
}
