<?php

namespace Database\Seeders;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * Справочник типов событий. Источник правды — EventTypeEnum:
     * id = значение кейса енама, name = slug (поле `event`, которое пишет аддон).
     * Таблица нужна для FK events.event_type_id и джойнов/метаданных.
     * id менять нельзя — на них ссылается events; новые типы добавляйте в енам.
     */
    public function run(): void
    {
        $rows = array_map(
            fn (EventTypeEnum $type) => ['id' => $type->value, 'name' => $type->slug()],
            EventTypeEnum::cases()
        );

        EventType::upsert($rows, ['id'], ['name']);
    }
}
