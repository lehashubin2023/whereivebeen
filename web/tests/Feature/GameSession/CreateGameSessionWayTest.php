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
        $mock = $this->partialMock(CreateWay::class, fn($mock) => $mock->shouldReceive('getChunkSize')->andReturn(2));
        $session = GameSession::factory()->create();
        $wayPointTable = (new WayPoint())->getTable();
        $eventTable = (new Event())->getTable();

        $mock->exec($session, [
            ['x' => 10, 'y' => 20, 'mapId' => null, 't' => 0],
            ['x' => 11, 'y' => 21, 'mapId' => null, 'event' => 'combat', 'inCombat' => true, 't' => 0],
            ['x' => 12, 'y' => 22, 'mapId' => null, 'event' => 'loot', 'itemId' => 999, 'itemName' => 'Sword', 'count' => 2, 't' => 0],
            ['x' => 13, 'y' => 23, 'mapId' => null, 'event' => 'unknown', 't' => 0],  
        ]);

        $this->assertDatabaseCount($wayPointTable, 4);
        $this->assertDatabaseCount($eventTable, 2);

        $this->assertDatabaseHas($wayPointTable, [
            'game_session_id' => $session->id,
            'sequence' => 2,
            'x' => 11, 
            'y' => 21
        ]);
        $this->assertDatabaseHas($wayPointTable, [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'x' => 13, 
            'y' => 23
        ]);

        $this->assertDatabaseHas($eventTable, [
            'game_session_id' => $session->id,
            'sequence' => 3,
            'event_type_id' => EventTypeEnum::LOOT,
            'payload' => $this->castAsJson(['item_id' => 999, 'item_name' => 'Sword', 'count' => 2]),
        ]);
        $this->assertDatabaseMissing($eventTable, [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'event_type_id' => null,
            'payload' => json_encode([])
        ]);
    }
}
