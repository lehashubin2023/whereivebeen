<?php

namespace Database\Seeders;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\EventType;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = array_map(
            fn (EventTypeEnum $type) => ['id' => $type->value, 'name' => $type->slug()],
            EventTypeEnum::cases()
        );

        EventType::upsert($rows, ['id'], ['name']);
    }
}
