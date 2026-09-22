<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\Import\ImportGameSession;
use App\Enums\GameSession\ImportStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Models\Map;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgress;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportNewMapTest extends TestCase
{
    use RefreshDatabase;

    private const KNOWN_MAP_ID = 1449;

    private const NEW_MAP_ID = 9999;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert([['id' => self::KNOWN_MAP_ID, 'name' => 'Test Zone']]);
    }

    private function payload(int $sessionId, int $mapId): string
    {
        return json_encode([
            'schema' => 2,
            'sessionId' => $sessionId,
            'started' => 1786128925,
            'char' => 'Valeriys',
            'realm' => 'Firemaw',
            'points' => [
                ['x' => 0.45, 'y' => 0.18, 'mapId' => $mapId, 't' => 10.0],
                ['x' => 0.46, 'y' => 0.19, 'mapId' => $mapId, 't' => 25.0],
            ],
        ]);
    }

    public function test_it_registers_an_unknown_map()
    {
        app()->make(ImportGameSession::class)->exec(
            $this->payload(1, self::NEW_MAP_ID),
            User::factory()->create()
        );

        $this->assertDatabaseHas('maps', [
            'id' => self::NEW_MAP_ID,
            'name' => 'Zone '.self::NEW_MAP_ID,
            'auto_added' => 1,
        ]);
    }

    public function test_the_points_of_a_new_map_keep_their_map_id()
    {
        app()->make(ImportGameSession::class)->exec(
            $this->payload(1, self::NEW_MAP_ID),
            User::factory()->create()
        );

        $this->assertDatabaseCount('way_points', 2);
        $this->assertDatabaseHas('way_points', ['map_id' => self::NEW_MAP_ID]);
        $this->assertDatabaseMissing('way_points', ['map_id' => null]);
    }

    public function test_it_lists_the_new_maps_in_the_import_log()
    {
        $user = User::factory()->create();
        $progress = new ImportGameSessionProgress($user);

        app()->make(ImportGameSession::class)->exec(
            $this->payload(1, self::NEW_MAP_ID),
            $user,
            $progress
        );

        $this->assertDatabaseHas('import_logs', [
            'id' => $progress->id(),
            'warnings' => $this->castAsJson(['new_maps' => [self::NEW_MAP_ID]]),
        ]);
    }

    public function test_a_known_map_produces_no_warning()
    {
        $user = User::factory()->create();
        $progress = new ImportGameSessionProgress($user);

        app()->make(ImportGameSession::class)->exec(
            $this->payload(1, self::KNOWN_MAP_ID),
            $user,
            $progress
        );

        $this->assertDatabaseCount('maps', 1);
        $this->assertDatabaseHas('import_logs', ['id' => $progress->id(), 'warnings' => null]);
    }

    public function test_a_second_import_of_the_same_new_zone_creates_one_map()
    {
        $user = User::factory()->create();
        $importer = app()->make(ImportGameSession::class);

        $importer->exec($this->payload(1, self::NEW_MAP_ID), $user);
        $importer->exec($this->payload(2, self::NEW_MAP_ID), $user);

        $this->assertDatabaseCount('maps', 2);
        $this->assertDatabaseCount('game_sessions', 2);
    }

    public function test_points_with_an_out_of_range_map_id_are_still_nulled()
    {
        $user = User::factory()->create();

        ImportGameSessionJob::dispatch($this->payload(1, 70000), $user);

        $this->assertDatabaseCount('maps', 1);
        $this->assertDatabaseHas('way_points', ['map_id' => null]);
        $this->assertDatabaseHas('import_logs', ['status' => ImportStatusEnum::COMPLETED]);
    }
}
