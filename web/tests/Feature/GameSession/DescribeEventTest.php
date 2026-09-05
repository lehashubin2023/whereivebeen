<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\DescribeEvent;
use App\Enums\GameSession\EventTypeEnum;
use Tests\TestCase;

class DescribeEventTest extends TestCase
{
    private DescribeEvent $describeEvent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->describeEvent = new DescribeEvent;
    }

    public function test_it_describes_level_up(): void
    {
        $this->assertSame(
            [['label' => 'Level', 'value' => '42']],
            $this->describeEvent->exec(EventTypeEnum::LEVELUP, ['level' => 42]),
        );
    }

    public function test_it_describes_loot_with_name_and_count(): void
    {
        $this->assertSame(
            [
                ['label' => 'Item', 'value' => 'Sword'],
                ['label' => 'Count', 'value' => '2'],
            ],
            $this->describeEvent->exec(EventTypeEnum::LOOT, [
                'item_id' => 999,
                'item_name' => 'Sword',
                'count' => 2,
            ]),
        );
    }

    public function test_it_falls_back_to_item_id_when_name_is_missing(): void
    {
        $this->assertSame(
            [['label' => 'Item', 'value' => '999']],
            $this->describeEvent->exec(EventTypeEnum::LOOT, ['item_id' => 999]),
        );
    }

    public function test_it_describes_quest(): void
    {
        $this->assertSame(
            [
                ['label' => 'Action', 'value' => 'Turned in'],
                ['label' => 'Quest', 'value' => 'Deep Ocean, Vast Sea'],
            ],
            $this->describeEvent->exec(EventTypeEnum::QUEST, [
                'action' => 'turnin',
                'quest_id' => 1234,
                'title' => 'Deep Ocean, Vast Sea',
            ]),
        );
    }

    public function test_it_describes_group(): void
    {
        $this->assertSame(
            [
                ['label' => 'Action', 'value' => 'Joined'],
                ['label' => 'Member', 'value' => 'Thrall'],
            ],
            $this->describeEvent->exec(EventTypeEnum::GROUP, [
                'action' => 'join',
                'member' => 'Thrall',
            ]),
        );
    }

    public function test_it_describes_boolean_states(): void
    {
        $this->assertSame(
            [['label' => 'State', 'value' => 'Mounted']],
            $this->describeEvent->exec(EventTypeEnum::MOUNT, ['mounted' => true]),
        );

        $this->assertSame(
            [['label' => 'State', 'value' => 'Dismounted']],
            $this->describeEvent->exec(EventTypeEnum::MOUNT, ['mounted' => false]),
        );

        $this->assertSame(
            [['label' => 'State', 'value' => 'Landing']],
            $this->describeEvent->exec(EventTypeEnum::TAXI, ['on_taxi' => false]),
        );
    }

    public function test_it_describes_visit(): void
    {
        $this->assertSame(
            [['label' => 'Place', 'value' => 'Merchant']],
            $this->describeEvent->exec(EventTypeEnum::VISIT, ['place' => 'merchant']),
        );
    }

    public function test_events_without_payload_have_no_details(): void
    {
        $this->assertSame([], $this->describeEvent->exec(EventTypeEnum::DEATH, []));
        $this->assertSame([], $this->describeEvent->exec(EventTypeEnum::RESURRECT, []));
    }

    public function test_every_marker_type_is_handled(): void
    {
        foreach (EventTypeEnum::cases() as $type) {
            if (! $type->hasMarker()) {
                continue;
            }

            $this->assertIsArray($this->describeEvent->exec($type, []));
            $this->assertNotSame('', $type->label());
        }
    }
}
