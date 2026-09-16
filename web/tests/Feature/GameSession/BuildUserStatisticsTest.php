<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\BuildUserStatistics;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildUserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const MAP_ID = 331;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert(['id' => self::MAP_ID, 'name' => 'Ashenvale']);
    }

    private function addEvent(GameSession $session, EventTypeEnum $type, array $payload = [], ?int $time = null): void
    {
        $sequence = ++$this->sequence;

        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => self::MAP_ID,
            'time' => $time ?? $sequence * 10,
            'x' => 0.5,
            'y' => 0.5,
        ]);

        Event::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'event_type_id' => $type->value,
            'payload' => $payload,
        ]);
    }

    private function build(User $user): array
    {
        return app(BuildUserStatistics::class)->exec($user);
    }

    private function group(array $statistics, string $slug): ?array
    {
        foreach ($statistics['groups'] as $group) {
            if ($group['slug'] === $slug) {
                return $group;
            }
        }

        return null;
    }

    private function table(array $group, string $title): ?array
    {
        foreach ($group['tables'] as $table) {
            if ($table['title'] === $title) {
                return $table;
            }
        }

        return null;
    }

    public function test_it_counts_events_of_every_type(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::DEATH, ['killer' => ['name' => 'Hogger']]);
        $this->addEvent($session, EventTypeEnum::RESURRECT);
        $this->addEvent($session, EventTypeEnum::RESURRECT);
        $this->addEvent($session, EventTypeEnum::RESURRECT);

        $statistics = $this->build($user);

        $this->assertSame('resurrect', $statistics['groups'][0]['slug']);
        $this->assertSame(3, $statistics['groups'][0]['total']);
        $this->assertSame(1, $this->group($statistics, 'death')['total']);
    }

    public function test_it_reports_who_killed_the_character(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $killer = ['name' => 'Originalstab', 'class' => 'ROGUE', 'pvp' => true, 'spell' => 'Eviscerate'];

        $this->addEvent($session, EventTypeEnum::DEATH, ['killer' => $killer]);
        $this->addEvent($session, EventTypeEnum::DEATH, ['killer' => $killer]);
        $this->addEvent($session, EventTypeEnum::DEATH, ['killer' => ['name' => 'Hogger', 'creatureType' => 'Humanoid']]);
        $this->addEvent($session, EventTypeEnum::DEATH, ['environment' => 'FALLING']);

        $group = $this->group($this->build($user), 'death');

        $this->assertSame(4, $group['total']);

        $killers = $this->table($group, 'Killed by');

        $this->assertSame(['Killer', 'Type', 'Deaths'], $killers['columns']);
        $this->assertSame([
            ['Originalstab', 'Rogue (player)', '2'],
            ['Hogger', 'Humanoid', '1'],
        ], $killers['rows']);

        $this->assertSame([['Eviscerate', '2']], $this->table($group, 'Killing blows')['rows']);
        $this->assertSame([['Falling', '1']], $this->table($group, 'Environment')['rows']);
    }

    public function test_it_splits_quests_into_accepted_and_turned_in(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::QUEST, ['action' => 'accept', 'title' => 'Wanted: Hogger']);
        $this->addEvent($session, EventTypeEnum::QUEST, ['action' => 'turnin', 'title' => 'Wanted: Hogger']);
        $this->addEvent($session, EventTypeEnum::QUEST, ['action' => 'accept', 'quest_id' => 10106]);

        $group = $this->group($this->build($user), 'quest');

        $this->assertSame([['Accepted', '2'], ['Turned in', '1']], $this->table($group, 'By action')['rows']);

        $quests = $this->table($group, 'Quests');

        $this->assertSame(['Quest', 'Accepted', 'Turned in'], $quests['columns']);
        $this->assertSame([
            ['Wanted: Hogger', '1', '1'],
            ['#10106', '1', '0'],
        ], $quests['rows']);
    }

    public function test_it_sums_looted_item_quantities(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::LOOT, ['items' => [
            ['id' => 858, 'name' => 'Linen Cloth', 'n' => 4],
            ['id' => 2589, 'name' => 'Rune Thread', 'n' => 1],
        ]]);
        $this->addEvent($session, EventTypeEnum::LOOT, ['items' => [
            ['id' => 858, 'name' => 'Linen Cloth', 'n' => 3],
        ]]);

        $items = $this->table($this->group($this->build($user), 'loot'), 'Items looted');

        $this->assertSame(['Item', 'Quantity', 'Drops'], $items['columns']);
        $this->assertSame([
            ['Linen Cloth', '7', '2'],
            ['Rune Thread', '1', '1'],
        ], $items['rows']);
    }

    public function test_it_renames_visit_places_and_counts_npcs(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::VISIT, [
            'places' => ['merchant', 'repair'],
            'npc_name' => 'Alexandra Constantine',
        ]);
        $this->addEvent($session, EventTypeEnum::VISIT, ['places' => ['flightmaster']]);

        $group = $this->group($this->build($user), 'visit');

        $this->assertSame([
            ['Flight master', '1'],
            ['Merchant', '1'],
            ['Repair', '1'],
        ], $this->table($group, 'By place')['rows']);

        $this->assertSame([['Alexandra Constantine', '1']], $this->table($group, 'NPCs')['rows']);
    }

    public function test_it_formats_time_away_for_route_gaps(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::GAP, ['reason' => 'login', 'seconds' => 7820]);
        $this->addEvent($session, EventTypeEnum::GAP, ['reason' => 'login', 'seconds' => 100]);

        $gaps = $this->table($this->group($this->build($user), 'gap'), 'By reason');

        $this->assertSame(['Reason', 'Times', 'Time away'], $gaps['columns']);
        $this->assertSame([['Login', '2', '2h 12m']], $gaps['rows']);
    }

    public function test_it_sorts_levels_naturally(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        foreach ([12, 9, 10] as $level) {
            $this->addEvent($session, EventTypeEnum::LEVELUP, ['level' => $level]);
        }

        $levels = $this->table($this->group($this->build($user), 'levelup'), 'Levels gained');

        $this->assertSame([['9', '1'], ['10', '1'], ['12', '1']], $levels['rows']);
    }

    public function test_it_summarises_the_whole_account(): void
    {
        $user = User::factory()->create();
        $first = GameSession::factory()->forUser($user)->create();
        $second = GameSession::factory()->forUser($user)->create();

        $this->addEvent($first, EventTypeEnum::DEATH, [], 0);
        $this->addEvent($first, EventTypeEnum::RESURRECT, [], 1800);
        $this->addEvent($second, EventTypeEnum::VISIT, [], 0);
        $this->addEvent($second, EventTypeEnum::GAP, ['reason' => 'login', 'seconds' => 60], 1200);

        $overview = collect($this->build($user)['overview'])
            ->pluck('value', 'label')
            ->all();

        $this->assertSame('2', $overview['Sessions']);
        $this->assertSame('4', $overview['Waypoints']);
        $this->assertSame('1', $overview['Zones visited']);
        $this->assertSame('4', $overview['Events']);
        $this->assertSame('4m', $overview['Time played']);
    }

    public function test_time_played_is_the_sum_of_the_session_durations(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->addEvent($session, EventTypeEnum::VISIT, [], 0);
        $this->addEvent($session, EventTypeEnum::VISIT, [], 1200);
        $this->addEvent($session, EventTypeEnum::VISIT, [], 451200);

        $overview = collect($this->build($user)['overview'])->pluck('value', 'label')->all();

        $this->assertSame('12m', $overview['Time played']);
    }

    public function test_it_ignores_sessions_of_other_users(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $this->addEvent(
            GameSession::factory()->forUser($stranger)->create(),
            EventTypeEnum::DEATH,
            ['killer' => ['name' => 'Hogger']],
        );

        $statistics = $this->build($user);

        $this->assertSame([], $statistics['groups']);
        $this->assertSame('0', collect($statistics['overview'])->firstWhere('label', 'Events')['value']);
    }

    public function test_the_page_is_available_to_authenticated_users_only(): void
    {
        $this->withoutVite();

        $this->get('/statistics')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/statistics')
            ->assertOk();
    }
}
