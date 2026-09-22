<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\Import\DecodeRawInput;
use App\Actions\GameSession\Import\ImportGameSession;
use App\Enums\GameSession\EventTypeEnum;
use App\Exceptions\GameSession\InvalidGameSessionInputException;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\Map;
use App\Models\User;
use App\Models\WayPoint;
use App\Validators\GameSessionJsonValidator;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportSchemaV2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EventTypeSeeder::class);

        Map::query()->insert([['id' => 1449, 'name' => 'Test Zone']]);
    }

    private function raw(): string
    {
        return file_get_contents($this->getFixturesPath('/game-sessions/valid-v2.txt'));
    }

    private function compressed(): string
    {
        return file_get_contents($this->getFixturesPath('/game-sessions/valid-v2-compressed.txt'));
    }

    public function test_it_decodes_compressed_export_into_the_same_payload(): void
    {
        $decoder = app()->make(DecodeRawInput::class);

        $this->assertSame(
            $decoder->exec($this->raw()),
            $decoder->exec($this->compressed()),
        );
    }

    public function test_it_rejects_broken_base64(): void
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid base64 input');

        app()->make(DecodeRawInput::class)->exec(DecodeRawInput::COMPRESSED_PREFIX.'!!! not base64 !!!');
    }

    public function test_it_rejects_payload_that_is_not_deflate(): void
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid compressed input');

        app()->make(DecodeRawInput::class)->exec(
            DecodeRawInput::COMPRESSED_PREFIX.base64_encode('plain text, never deflated'),
        );
    }

    public function test_it_still_accepts_uncompressed_json(): void
    {
        $decoded = app()->make(DecodeRawInput::class)->exec($this->raw());

        $this->assertSame(2, $decoded['schema']);
        $this->assertNotEmpty($decoded['points']);
    }

    public function test_it_validates_collapsed_events(): void
    {
        $validated = GameSessionJsonValidator::validate(
            app()->make(DecodeRawInput::class)->exec($this->raw()),
        );

        $this->assertSame('Tester', $validated['char']);
        $this->assertNotNull($validated['ended']);
        $this->assertSame('ruRU', $validated['locale']);
        $this->assertSame('2.0.0', $validated['addon']);

        $loot = $this->findPoint($validated['points'], 'loot');

        $this->assertCount(2, $loot['items']);
        $this->assertSame(2589, $loot['items'][0]['id']);
        $this->assertSame(3, $loot['items'][0]['n']);
    }

    public function test_it_imports_collapsed_payloads_into_events(): void
    {
        $user = User::factory()->create();

        $sessionId = app()->make(ImportGameSession::class)->exec($this->compressed(), $user);

        $session = GameSession::query()->findOrFail($sessionId);

        $this->assertSame('Tester', $session->character);
        $this->assertSame(2, $session->schema_version);
        $this->assertSame('ruRU', $session->locale);
        $this->assertSame('2.0.0', $session->addon_version);
        $this->assertSame('11.2.7', $session->game_version);
        $this->assertSame('HORDE', strtoupper((string) $session->faction));
        $this->assertNotNull($session->session_end_at);

        $loot = $this->eventPayload($session, EventTypeEnum::LOOT);
        $this->assertCount(2, $loot['items']);
        $this->assertSame('Полотняная ткань', $loot['items'][0]['name']);

        $gather = $this->eventPayload($session, EventTypeEnum::GATHER);
        $this->assertSame(1731, $gather['node']['objectId']);

        $death = $this->eventPayload($session, EventTypeEnum::DEATH);
        $this->assertSame(1412, $death['killer']['npcId']);
        $this->assertFalse($death['killer']['pvp']);

        $visit = $this->eventPayload($session, EventTypeEnum::VISIT);
        $this->assertSame(['merchant', 'repair'], $visit['places']);
        $this->assertSame(448, $visit['npc_id']);

        $zone = $this->eventPayload($session, EventTypeEnum::ZONE);
        $this->assertSame('Elwynn Forest', $zone['zone']);
        $this->assertSame('Goldshire', $zone['sub_zone']);

        $gap = $this->eventPayload($session, EventTypeEnum::GAP);
        $this->assertSame('spell', $gap['reason']);
        $this->assertSame(8690, $gap['spell_id']);
    }

    public function test_it_stores_time_in_deciseconds_without_losing_tenths(): void
    {
        $user = User::factory()->create();

        $payload = json_decode($this->raw(), true, DecodeRawInput::MAX_DEPTH);
        $payload['sessionId'] = 1700000008888;
        $payload['points'] = [
            ['x' => 0.5, 'y' => 0.5, 'mapId' => 1449, 't' => 63.4],
            ['x' => 0.6, 'y' => 0.6, 'mapId' => 1449, 't' => 1801.9],
        ];

        $sessionId = app()->make(ImportGameSession::class)->exec(json_encode($payload), $user);

        $stored = WayPoint::query()
            ->where('game_session_id', $sessionId)
            ->orderBy('sequence')
            ->pluck('time')
            ->map(fn ($time) => (int) $time)
            ->all();

        $this->assertSame([634, 18019], $stored);
    }

    public function test_it_keeps_events_that_lost_their_position(): void
    {
        $user = User::factory()->create();

        $payload = json_decode($this->raw(), true, DecodeRawInput::MAX_DEPTH);
        $payload['sessionId'] = 1700000009999;
        $payload['points'] = [
            ['x' => 0, 'y' => 0, 't' => 1.5, 'event' => 'death'],
            ['x' => 0.5, 'y' => 0.5, 'mapId' => 1449, 't' => 2.5],
        ];

        $sessionId = app()->make(ImportGameSession::class)->exec(json_encode($payload), $user);

        $this->assertDatabaseHas((new WayPoint)->getTable(), [
            'game_session_id' => $sessionId,
            'sequence' => 1,
            'map_id' => null,
        ]);

        $this->assertDatabaseHas((new Event)->getTable(), [
            'game_session_id' => $sessionId,
            'sequence' => 1,
            'event_type_id' => EventTypeEnum::DEATH->value,
        ]);
    }

    public function test_it_accepts_sequences_beyond_the_old_smallint_limit(): void
    {
        $user = User::factory()->create();
        $session = GameSession::factory()->forUser($user)->create();

        WayPoint::query()->create([
            'game_session_id' => $session->id,
            'sequence' => 70000,
            'map_id' => null,
            'time' => 1234567,
            'x' => 0.5,
            'y' => 0.5,
        ]);

        $this->assertDatabaseHas((new WayPoint)->getTable(), [
            'game_session_id' => $session->id,
            'sequence' => 70000,
            'time' => 1234567,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $points
     * @return array<string, mixed>
     */
    private function findPoint(array $points, string $event): array
    {
        foreach ($points as $point) {
            if (($point['event'] ?? null) === $event) {
                return $point;
            }
        }

        $this->fail("point with event {$event} not found");
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(GameSession $session, EventTypeEnum $type): array
    {
        $event = Event::query()
            ->where('game_session_id', $session->id)
            ->where('event_type_id', $type->value)
            ->firstOrFail();

        return (array) $event->payload;
    }
}
