<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * Справочник типов событий. Значения name соответствуют полю `event`
     * в точках, которые пишет аддон (см. addon/Core.lua, addon/Methods.lua).
     * id фиксированы — на них ссылается events.event_type_id, поэтому
     * существующие пары id→name менять нельзя, новые добавлять в конец.
     */
    private const TYPES = [
        1  => 'mount',
        2  => 'combat',
        3  => 'death',
        4  => 'resurrect',
        5  => 'levelup',
        6  => 'loot',
        7  => 'visit',
        8  => 'group',
        9  => 'quest',
        10 => 'taxi',
        11 => 'gap',
    ];

    public function run(): void
    {
        $rows = [];
        foreach (self::TYPES as $id => $name) {
            $rows[] = ['id' => $id, 'name' => $name];
        }

        EventType::upsert($rows, ['id'], ['name']);
    }
}
