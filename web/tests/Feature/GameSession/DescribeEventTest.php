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
            [['label' => 'Item', 'value' => 'Sword x2']],
            $this->describeEvent->exec(EventTypeEnum::LOOT, [
                'items' => [['id' => 999, 'name' => 'Sword', 'n' => 2]],
            ]),
        );
    }

    public function test_it_falls_back_to_item_id_when_name_is_missing(): void
    {
        $this->assertSame(
            [['label' => 'Item', 'value' => '999 x1']],
            $this->describeEvent->exec(EventTypeEnum::LOOT, [
                'items' => [['id' => 999, 'n' => 1]],
            ]),
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
            [['label' => 'Joined', 'value' => 'Thrall']],
            $this->describeEvent->exec(EventTypeEnum::GROUP, [
                'joined' => ['Thrall'],
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
            [['label' => 'Places', 'value' => 'Merchant, Repair']],
            $this->describeEvent->exec(EventTypeEnum::VISIT, ['places' => ['merchant', 'repair']]),
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

    public function test_it_describes_collapsed_loot(): void
    {
        $this->assertSame(
            [
                ['label' => 'Item', 'value' => 'Linen Cloth x3'],
                ['label' => 'Item', 'value' => 'Malachite x1'],
            ],
            $this->describeEvent->exec(EventTypeEnum::LOOT, [
                'items' => [
                    ['id' => 2589, 'name' => 'Linen Cloth', 'n' => 3],
                    ['id' => 774, 'name' => 'Malachite', 'n' => 1],
                ],
            ]),
        );
    }

    public function test_it_describes_collapsed_visit(): void
    {
        $this->assertSame(
            [
                ['label' => 'Places', 'value' => 'Merchant, Repair'],
                ['label' => 'NPC', 'value' => 'Torv'],
            ],
            $this->describeEvent->exec(EventTypeEnum::VISIT, [
                'places' => ['merchant', 'repair'],
                'npc_name' => 'Torv',
            ]),
        );
    }

    public function test_it_describes_collapsed_group(): void
    {
        $this->assertSame(
            [
                ['label' => 'Joined', 'value' => 'Alpha, Beta'],
                ['label' => 'Left', 'value' => 'Gamma'],
            ],
            $this->describeEvent->exec(EventTypeEnum::GROUP, [
                'joined' => ['Alpha', 'Beta'],
                'left' => ['Gamma'],
            ]),
        );
    }

    public function test_it_describes_zone(): void
    {
        $this->assertSame(
            [
                ['label' => 'Zone', 'value' => 'Westfall'],
                ['label' => 'Subzone', 'value' => 'Sentinel Hill'],
            ],
            $this->describeEvent->exec(EventTypeEnum::ZONE, [
                'zone' => 'Westfall',
                'sub_zone' => 'Sentinel Hill',
            ]),
        );
    }

    public function test_it_describes_gather(): void
    {
        $this->assertSame(
            [
                ['label' => 'Node', 'value' => 'Copper Vein'],
                ['label' => 'Item', 'value' => 'Copper Ore x2'],
            ],
            $this->describeEvent->exec(EventTypeEnum::GATHER, [
                'node' => ['name' => 'Copper Vein', 'objectId' => 1731],
                'items' => [['id' => 2770, 'name' => 'Copper Ore', 'n' => 2]],
            ]),
        );
    }

    public function test_it_describes_death_by_creature(): void
    {
        $this->assertSame(
            [
                ['label' => 'Killed by', 'value' => 'Kobold Miner'],
                ['label' => 'Ability', 'value' => 'Fireball'],
                ['label' => 'Damage', 'value' => '148'],
            ],
            $this->describeEvent->exec(EventTypeEnum::DEATH, [
                'killer' => ['name' => 'Kobold Miner', 'spell' => 'Fireball', 'amount' => 148, 'pvp' => false],
            ]),
        );
    }

    public function test_it_describes_death_by_player(): void
    {
        $this->assertSame(
            [['label' => 'Killed by player', 'value' => 'Ganker']],
            $this->describeEvent->exec(EventTypeEnum::DEATH, [
                'killer' => ['name' => 'Ganker', 'pvp' => true],
            ]),
        );
    }

    public function test_it_describes_environmental_death(): void
    {
        $this->assertSame(
            [['label' => 'Cause', 'value' => 'Falling']],
            $this->describeEvent->exec(EventTypeEnum::DEATH, ['environment' => 'FALLING']),
        );
    }
}
