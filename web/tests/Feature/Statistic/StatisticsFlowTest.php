<?php

namespace Tests\Feature\Statistic;

use App\Jobs\ImportGameSessionJob;
use App\Models\GameSession;
use App\Models\SessionEventCount;
use App\Models\SessionMapStat;
use App\Models\SessionStatisticEntry;
use App\Models\User;
use Database\Seeders\EventTypeSeeder;
use Database\Seeders\MapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);
        $this->seed(MapSeeder::class);
    }

    private function import(User $user, string $fixture): GameSession
    {
        ImportGameSessionJob::dispatch(
            (string) file_get_contents($this->getFixturesPath('/game-sessions/'.$fixture)),
            $user,
        );

        return GameSession::query()->where('user_id', $user->id)->firstOrFail();
    }

    public function test_an_import_writes_the_session_facts(): void
    {
        $user = User::factory()->create();
        $session = $this->import($user, 'valid1.txt');

        $this->assertGreaterThan(0, $session->points_count);
        $this->assertGreaterThan(0, $session->duration_seconds);

        foreach ([SessionStatisticEntry::class, SessionEventCount::class, SessionMapStat::class] as $model) {
            $this->assertGreaterThan(0, $model::query()->where('user_id', $user->id)->count());
        }
    }

    public function test_reimporting_a_session_leaves_one_set_of_facts(): void
    {
        $user = User::factory()->create();

        $this->import($user, 'valid1.txt');
        $before = SessionStatisticEntry::query()->count();

        $this->import($user, 'valid1.txt');

        $this->assertDatabaseCount('game_sessions', 1);
        $this->assertSame($before, SessionStatisticEntry::query()->count());
    }
}
