<?php

namespace Tests\Feature\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use Tests\TestCase;

class EventTypeEnumTest extends TestCase
{
    public function test_from_slug_round_trips_every_case()
    {
        foreach (EventTypeEnum::cases() as $case) {
            $this->assertSame($case, EventTypeEnum::fromSlug($case->slug()));
        }
    }

    public function test_unknown_slug_returns_null()
    {
        $this->assertNull(EventTypeEnum::fromSlug('does-not-exist'));
    }

    public function test_every_addon_event_has_enum_case()
    {
        $addonEvents = ['mount', 'combat', 'death', 'resurrect', 'levelup', 'loot', 'visit', 'group', 'quest', 'taxi', 'gap'];

        foreach ($addonEvents as $slug) {
            $this->assertNotNull(EventTypeEnum::fromSlug($slug), "Нет EventTypeEnum для события аддона: {$slug}");
        }
    }
}
