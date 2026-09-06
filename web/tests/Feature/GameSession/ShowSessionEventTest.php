<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\User;
use App\Models\WayPoint;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowSessionEventTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);
    }

    private function makeEvent(
        GameSession $session,
        int $sequence,
        EventTypeEnum $type,
        array $payload = [],
    ): void {
        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => $sequence,
            'map_id' => null,
            'time' => 600,
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

    private function url(GameSession $session, int $sequence): string
    {
        return "/game-session/sessions/{$session->id}/events/{$sequence}";
    }

    public function test_it_returns_event_details_for_the_owner(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->makeEvent($session, 3, EventTypeEnum::LEVELUP, ['level' => 42]);

        $response = $this->actingAs($user)->getJson($this->url($session, 3));

        $response->assertOk()->assertJson([
            'sequence' => 3,
            'type' => 'levelup',
            'label' => 'Level up',
            'details' => [
                ['label' => 'Level', 'value' => '42'],
            ],
        ]);

        $this->assertSame(
            $session->session_start_at->copy()->addSeconds(60)->toIso8601String(),
            $response->json('time'),
        );
    }

    public function test_it_forbids_access_to_a_foreign_session(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $session = GameSession::factory()->forUser($owner)->create();

        $this->makeEvent($session, 1, EventTypeEnum::DEATH);

        $this->actingAs($stranger)
            ->getJson($this->url($session, 1))
            ->assertForbidden();
    }

    public function test_it_requires_authentication(): void
    {
        $session = GameSession::factory()->create();

        $this->getJson($this->url($session, 1))->assertUnauthorized();
    }

    public function test_it_returns_404_when_the_sequence_has_no_event(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->actingAs($user)
            ->getJson($this->url($session, 77))
            ->assertNotFound();
    }

    public function test_it_returns_404_for_types_without_a_marker(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->makeEvent($session, 1, EventTypeEnum::GAP);
        $this->makeEvent($session, 2, EventTypeEnum::COMBAT, ['in_combat' => true]);

        $this->actingAs($user)->getJson($this->url($session, 1))->assertNotFound();
        $this->actingAs($user)->getJson($this->url($session, 2))->assertNotFound();
    }

    public function test_it_caches_the_event(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        $this->makeEvent($session, 5, EventTypeEnum::VISIT, ['places' => ['bank']]);

        $this->actingAs($user)->getJson($this->url($session, 5))->assertOk();

        Event::query()
            ->where('game_session_id', $session->id)
            ->where('sequence', 5)
            ->delete();

        $this->actingAs($user)
            ->getJson($this->url($session, 5))
            ->assertOk()
            ->assertJsonPath('details.0.value', 'Bank');
    }
}
