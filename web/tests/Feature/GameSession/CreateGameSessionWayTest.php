<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\CreateWay;
use App\Models\Event;
use App\Models\EventType;
use App\Models\GameSession;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\Attributes\Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

#[Seeder(EventTypeSeeder::class)]
class CreateGameSessionWayTest extends TestCase
{
    use RefreshDatabase;

    public function test_way_session_created_correctly()
    {
        $mock = $this->partialMock(CreateWay::class, fn($mock) => $mock->shouldReceive('getChunkSize')->andReturn(2));
        // $createWay = app()->make(CreateWay::class);
        $session = GameSession::factory()->create();
        $eventTypes = EventType::get()->pluck('id', 'name');

        $mock->exec($session, [
            $this->point(['x' => 10, 'y' => 20, 'mapId' => null]),
            $this->point(['x' => 11, 'y' => 21, 'mapId' => null, 'event' => 'combat', 'inCombat' => true]),
            $this->point(['x' => 12, 'y' => 22, 'mapId' => null, 'event' => 'loot', 'itemId' => 999, 'itemName' => 'Sword', 'count' => 2]),
            $this->point(['x' => 13, 'y' => 23, 'mapId' => null, 'event' => 'unknown']),  
        ]);

        $this->assertDatabaseCount((new WayPoint())->getTable(), 4);
        $this->assertDatabaseCount((new Event())->getTable(), 3);

        $this->assertDatabaseHas((new WayPoint())->getTable(), [
            'game_session_id' => $session->id,
            'sequence' => 2,
            'x' => 11, 
            'y' => 21
        ]);
        $this->assertDatabaseHas((new WayPoint())->getTable(), [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'x' => 13, 
            'y' => 23
        ]);

        $this->assertDatabaseHas((new Event())->getTable(), [
            'game_session_id' => $session->id,
            'sequence' => 3,
            'event_type_id' => $eventTypes['loot'],
            'payload' => json_encode(['itemId' => 999, 'itemName' => 'Sword', 'count' => 2]),
        ]);
        $this->assertDatabaseHas((new Event())->getTable(), [
            'game_session_id' => $session->id,
            'sequence' => 4,
            'event_type_id' => $eventTypes['unknown'],
            'payload' => json_encode([])
        ]);

        $this->assertEquals(3, DB::transactionLevel());
    }

    private function point(array $overrides = []): array
    {
        return array_merge([
            'x' => 0, 'y' => 0, 'mapId' => 1, 't' => 0,
        ], $overrides);
    }
}
