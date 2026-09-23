<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildSessionList;
use App\Actions\Statistic\CollectStoredSessionStatistics;
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

        app(CollectStoredSessionStatistics::class)->exec($session);

        $row = $this->firstRow($user);

        $this->assertSame(120, $row['duration']);
        $this->assertSame(3, $row['points_count']);
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
}
