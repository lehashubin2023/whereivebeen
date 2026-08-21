<?php

namespace Tests\Feature\GameSession;

use App\DTOs\GameSession\CreateEventDTO;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\DTOs\GameSession\CreateImportLogDTO;
use App\DTOs\GameSession\CreateWayPointDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Enums\GameSession\ImportStatusEnum;
use Tests\TestCase;

/**
 * DTO проверяются против реального экспорта аддона (fixtures/game-sessions/valid1.txt)
 * и против точной формы точки, которую пишет addon/Methods.lua и addon/Core.lua.
 */
class DTOTest extends TestCase
{
    /**
     * Реальный экспорт аддона: JSON-строка → массив.
     */
    private function addonExport(): array
    {
        return json_decode(
            file_get_contents($this->getFixturesPath('/game-sessions/valid1.txt')),
            true
        );
    }

    public function test_create_game_session_dto_maps_addon_export()
    {
        $export = $this->addonExport();

        $dto = CreateGameSessionDTO::fromArray($export)->toArray();

        // Ключи аддона (char/sessionId/started) → колонки БД
        $this->assertSame($export['char'], $dto['character']);
        $this->assertSame($export['sessionId'], $dto['game_session_id']);
        $this->assertSame($export['started'], $dto['session_start_at']);
        $this->assertSame($export['realm'], $dto['realm']);
        $this->assertSame((string) $export['version'], $dto['version']);
        $this->assertNull($dto['user_id']);
    }

    public function test_create_way_point_dto_maps_addon_point_keys()
    {
        // Форма точки — как её пишет аддон (addon/Methods.lua: x, y, mapId, t)
        $point = ['x' => 1, 'y' => 2, 'mapId' => 1422, 't' => 63];

        $dto = CreateWayPointDTO::fromPoint($point, gameSessionId: 7, sequence: 3)->toArray();

        $this->assertSame(7, $dto['game_session_id']);
        $this->assertSame(1422, $dto['map_id']);   // mapId → map_id
        $this->assertSame(63, $dto['time']);        // t → time
        $this->assertSame(3, $dto['sequence']);
        $this->assertSame(1, $dto['x']);
        $this->assertSame(2, $dto['y']);
    }

    public function test_create_event_dto_maps_addon_event_keys()
    {
        // Все событийные ключи, которые реально эмитит аддон (addon/Methods.lua, addon/Core.lua)
        $point = [
            'x' => 1, 'y' => 2, 'mapId' => 10, 't' => 5,
            'event'    => 'loot',
            'level'    => 60,
            'action'   => 'join',
            'title'    => 'A Quest',
            'place'    => 'Inn',
            'member'   => 'Thrall',
            'inCombat' => true,
            'mounted'  => true,
            'onTaxi'   => false,
            'questId'  => 123,
            'itemId'   => 999,
            'itemName' => 'Sword',
            'count'    => 2,
        ];

        $dto = CreateEventDTO::fromPoint($point, gameSessionId: 1, sequence: 5, eventTypeId: EventTypeEnum::LOOT->value)->toArray();

        $this->assertSame(1, $dto['game_session_id']);
        $this->assertSame(5, $dto['sequence']);
        $this->assertSame(EventTypeEnum::LOOT->value, $dto['event_type_id']);

        $payload = json_decode($dto['payload'], true);

        // camelCase аддона → snake_case payload
        $this->assertSame(60, $payload['level']);
        $this->assertSame('join', $payload['action']);
        $this->assertSame('A Quest', $payload['title']);
        $this->assertSame('Inn', $payload['place']);
        $this->assertSame('Thrall', $payload['member']);
        $this->assertTrue($payload['in_combat']);
        $this->assertTrue($payload['mounted']);
        $this->assertFalse($payload['on_taxi']);
        $this->assertSame(123, $payload['quest_id']);
        $this->assertSame(999, $payload['item_id']);
        $this->assertSame('Sword', $payload['item_name']);
        $this->assertSame(2, $payload['count']);

        // Координаты в payload не дублируются (в схеме event берёт их по seq)
        $this->assertArrayNotHasKey('x', $payload);
        $this->assertArrayNotHasKey('mapId', $payload);
    }

    public function test_create_event_dto_reads_real_addon_event()
    {
        $export = $this->addonExport();

        $eventPoint = null;
        foreach ($export['points'] as $point) {
            if (! empty($point['event'])) {
                $eventPoint = $point;
                break;
            }
        }

        $this->assertNotNull($eventPoint, 'В экспорте аддона нет ни одной событийной точки');

        $type = EventTypeEnum::fromSlug($eventPoint['event']);
        $this->assertNotNull($type, "Аддон эмитит событие без пары в EventTypeEnum: {$eventPoint['event']}");

        $dto = CreateEventDTO::fromPoint($eventPoint, gameSessionId: 1, sequence: 1, eventTypeId: $type->value)->toArray();

        $this->assertSame($type->value, $dto['event_type_id']);
        $this->assertIsArray(json_decode($dto['payload'], true));
    }

    public function test_create_import_log_dto_defaults_and_mapping()
    {
        $default = CreateImportLogDTO::fromArray([])->toArray();

        $this->assertNull($default['game_session_id']);
        $this->assertSame(ImportStatusEnum::NEW, $default['status']);
        $this->assertSame(0, $default['execution_time']);
        $this->assertNull($default['error_message']);

        $mapped = CreateImportLogDTO::fromArray([
            'game_session_id' => 5,
            'status'          => 'completed',
            'execution_time'  => 3,
            'error_message'   => 'oops',
        ])->toArray();

        $this->assertSame(5, $mapped['game_session_id']);
        $this->assertSame(ImportStatusEnum::COMPLETED, $mapped['status']);
        $this->assertSame(3, $mapped['execution_time']);
        $this->assertSame('oops', $mapped['error_message']);
    }
}
