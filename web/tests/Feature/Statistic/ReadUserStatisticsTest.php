<?php

namespace Tests\Feature\Statistic;

use App\Actions\Statistic\CollectSessionStatistics;
use App\Actions\Statistic\ReadUserStatistics;
use App\Actions\Statistic\Rebuild\RebuildUserStatistics;
use App\Actions\Statistic\StoreSessionStatistics;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Support\Statistic\PointSource\ImportedPointSource;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadUserStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private const NAGRAND = 200;

    private int $time = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert(['id' => self::NAGRAND, 'name' => 'Nagrand']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function read(User $user, array $events): array
    {
        $session = GameSession::factory()->forUser($user)->create();

        $points = array_map(function (array $event): array {
            $this->time += 10;

            return ['x' => 0.5, 'y' => 0.5, 't' => $this->time, 'mapId' => self::NAGRAND, ...$event];
        }, $events);

        app(StoreSessionStatistics::class)->exec(
            $session,
            app(CollectSessionStatistics::class)->exec(new ImportedPointSource($points)),
        );

        app(RebuildUserStatistics::class)->exec($user);

        return app(ReadUserStatistics::class)->exec($user);
    }

    /**
     * @param  array<string, mixed>  $statistics
     * @return array<string, mixed>|null
     */
    private function group(array $statistics, string $slug): ?array
    {
        foreach ($statistics['groups'] as $group) {
            if ($group['slug'] === $slug) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>|null
     */
    private function table(array $group, string $title): ?array
    {
        foreach ($group['tables'] as $table) {
            if ($table['title'] === $title) {
                return $table;
            }
        }

        return null;
    }

    public function test_it_names_and_ranks_the_event_groups(): void
    {
        $statistics = $this->read(User::factory()->create(), [
            ['event' => 'death', 'killer' => ['name' => 'Hogger']],
            ['event' => 'resurrect'],
            ['event' => 'resurrect'],
            ['event' => 'resurrect'],
        ]);

        $this->assertSame('resurrect', $statistics['groups'][0]['slug']);
        $this->assertSame('Resurrect', $statistics['groups'][0]['label']);
        $this->assertSame(3, $statistics['groups'][0]['total']);
        $this->assertSame(1, $this->group($statistics, 'death')['total']);
    }

    public function test_it_describes_who_killed_the_character(): void
    {
        $killer = ['name' => 'Originalstab', 'class' => 'ROGUE', 'pvp' => true, 'spell' => 'Eviscerate'];

        $group = $this->group($this->read(User::factory()->create(), [
            ['event' => 'death', 'killer' => $killer],
            ['event' => 'death', 'killer' => $killer],
            ['event' => 'death', 'killer' => ['name' => 'Hogger', 'creatureType' => 'Humanoid']],
            ['event' => 'death', 'environment' => 'FALLING'],
        ]), 'death');

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
        $group = $this->group($this->read(User::factory()->create(), [
            ['event' => 'quest', 'action' => 'accept', 'title' => 'Wanted: Hogger'],
            ['event' => 'quest', 'action' => 'turnin', 'title' => 'Wanted: Hogger'],
            ['event' => 'quest', 'action' => 'accept', 'questId' => 10106],
        ]), 'quest');

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
        $items = $this->table($this->group($this->read(User::factory()->create(), [
            ['event' => 'loot', 'items' => [
                ['id' => 858, 'name' => 'Linen Cloth', 'n' => 4],
                ['id' => 2589, 'name' => 'Rune Thread', 'n' => 1],
            ]],
            ['event' => 'loot', 'items' => [['id' => 858, 'name' => 'Linen Cloth', 'n' => 3]]],
        ]), 'loot'), 'Items looted');

        $this->assertSame(['Item', 'Quantity', 'Drops'], $items['columns']);
        $this->assertSame([
            ['Linen Cloth', '7', '2'],
            ['Rune Thread', '1', '1'],
        ], $items['rows']);
    }

    public function test_it_translates_visit_places_and_counts_npcs(): void
    {
        $group = $this->group($this->read(User::factory()->create(), [
            ['event' => 'visit', 'places' => ['merchant', 'repair'], 'npcName' => 'Alexandra Constantine'],
            ['event' => 'visit', 'places' => ['flightmaster']],
        ]), 'visit');

        $this->assertSame([
            ['Flight master', '1'],
            ['Merchant', '1'],
            ['Repair', '1'],
        ], $this->table($group, 'By place')['rows']);

        $this->assertSame([['Alexandra Constantine', '1']], $this->table($group, 'NPCs')['rows']);
    }

    public function test_it_formats_time_away_for_route_gaps(): void
    {
        $gaps = $this->table($this->group($this->read(User::factory()->create(), [
            ['event' => 'gap', 'reason' => 'login', 'seconds' => 7820],
            ['event' => 'gap', 'reason' => 'login', 'seconds' => 100],
        ]), 'gap'), 'By reason');

        $this->assertSame(['Reason', 'Times', 'Time away'], $gaps['columns']);
        $this->assertSame([['Login', '2', '2h 12m']], $gaps['rows']);
    }

    public function test_it_sorts_levels_naturally(): void
    {
        $levels = $this->table($this->group($this->read(User::factory()->create(), [
            ['event' => 'levelup', 'level' => 12],
            ['event' => 'levelup', 'level' => 9],
            ['event' => 'levelup', 'level' => 10],
        ]), 'levelup'), 'Levels gained');

        $this->assertSame([['9', '1'], ['10', '1'], ['12', '1']], $levels['rows']);
    }

    public function test_it_summarises_the_account_and_names_the_zones(): void
    {
        $statistics = $this->read(User::factory()->create(), [
            ['event' => 'death'],
            ['event' => 'resurrect'],
        ]);

        $overview = collect($statistics['overview'])->pluck('value', 'label')->all();

        $this->assertSame('1', $overview['Sessions']);
        $this->assertSame('2', $overview['Waypoints']);
        $this->assertSame('1', $overview['Zones visited']);
        $this->assertSame('2', $overview['Events']);
        $this->assertSame('10s', $overview['Time played']);

        $this->assertSame('Nagrand', $statistics['journey']['zones'][0]['name']);
        $this->assertSame(['name' => 'Nagrand', 'deaths' => 1], $statistics['journey']['deadliest']);
        $this->assertFalse($statistics['stale']);
    }

    public function test_an_account_without_statistics_reads_as_empty(): void
    {
        $statistics = app(ReadUserStatistics::class)->exec(User::factory()->create());

        $this->assertSame([], $statistics['groups']);
        $this->assertSame('0', collect($statistics['overview'])->firstWhere('label', 'Events')['value']);
        $this->assertSame([], $statistics['journey']['zones']);
        $this->assertNull($statistics['journey']['deadliest']);
        $this->assertFalse($statistics['stale']);
    }

    public function test_the_page_is_available_to_authenticated_users_only(): void
    {
        $this->get('/statistics')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/statistics')
            ->assertOk();
    }
}
