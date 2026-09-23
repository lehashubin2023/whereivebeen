<?php

namespace App\Enums\Statistic;

use App\Enums\GameSession\EventTypeEnum;

enum StatisticBucketEnum: int
{
    case DEATH_KILLER = 1;
    case DEATH_SPELL = 2;
    case DEATH_CAUSE = 3;
    case QUEST_ACTION = 4;
    case QUEST_TITLE = 5;
    case LOOT_ITEM = 6;
    case GATHER_PROF = 7;
    case GATHER_NODE = 8;
    case GATHER_ITEM = 9;
    case VISIT_PLACE = 10;
    case VISIT_NPC = 11;
    case GROUP_MEMBER = 12;
    case ZONE_NAME = 13;
    case ZONE_SUB = 14;
    case LEVELUP_LEVEL = 15;
    case MOUNT_STATE = 16;
    case TAXI_STATE = 17;
    case COMBAT_STATE = 18;
    case GAP_REASON = 19;

    public const KEY_LENGTH = 160;

    private const PLACES = [
        'merchant' => 'Merchant',
        'repair' => 'Repair',
        'bank' => 'Bank',
        'guildbank' => 'Guild bank',
        'auction' => 'Auction house',
        'mail' => 'Mailbox',
        'trainer' => 'Trainer',
        'flightmaster' => 'Flight master',
        'stable' => 'Stable',
        'barber' => 'Barber',
        'trade' => 'Trade',
    ];

    public function slug(): string
    {
        return match ($this) {
            self::DEATH_KILLER => 'death.killer',
            self::DEATH_SPELL => 'death.spell',
            self::DEATH_CAUSE => 'death.cause',
            self::QUEST_ACTION => 'quest.action',
            self::QUEST_TITLE => 'quest.title',
            self::LOOT_ITEM => 'loot.item',
            self::GATHER_PROF => 'gather.prof',
            self::GATHER_NODE => 'gather.node',
            self::GATHER_ITEM => 'gather.item',
            self::VISIT_PLACE => 'visit.place',
            self::VISIT_NPC => 'visit.npc',
            self::GROUP_MEMBER => 'group.member',
            self::ZONE_NAME => 'zone.name',
            self::ZONE_SUB => 'zone.sub',
            self::LEVELUP_LEVEL => 'levelup.level',
            self::MOUNT_STATE => 'mount.state',
            self::TAXI_STATE => 'taxi.state',
            self::COMBAT_STATE => 'combat.state',
            self::GAP_REASON => 'gap.reason',
        };
    }

    public function eventType(): EventTypeEnum
    {
        return match ($this) {
            self::DEATH_KILLER, self::DEATH_SPELL, self::DEATH_CAUSE => EventTypeEnum::DEATH,
            self::QUEST_ACTION, self::QUEST_TITLE => EventTypeEnum::QUEST,
            self::LOOT_ITEM => EventTypeEnum::LOOT,
            self::GATHER_PROF, self::GATHER_NODE, self::GATHER_ITEM => EventTypeEnum::GATHER,
            self::VISIT_PLACE, self::VISIT_NPC => EventTypeEnum::VISIT,
            self::GROUP_MEMBER => EventTypeEnum::GROUP,
            self::ZONE_NAME, self::ZONE_SUB => EventTypeEnum::ZONE,
            self::LEVELUP_LEVEL => EventTypeEnum::LEVELUP,
            self::MOUNT_STATE => EventTypeEnum::MOUNT,
            self::TAXI_STATE => EventTypeEnum::TAXI,
            self::COMBAT_STATE => EventTypeEnum::COMBAT,
            self::GAP_REASON => EventTypeEnum::GAP,
        };
    }

    public function keyLabel(string $key): string
    {
        return match ($this) {
            self::VISIT_PLACE => (string) __(self::PLACES[$key] ?? ucfirst($key)),
            self::QUEST_ACTION => (string) __($key === 'turnin' ? 'Turned in' : 'Accepted'),
            self::MOUNT_STATE => (string) __($key === 'mounted' ? 'Mounted' : 'Dismounted'),
            self::TAXI_STATE => (string) __($key === 'takeoff' ? 'Takeoff' : 'Landing'),
            self::COMBAT_STATE => (string) __($key === 'entered' ? 'Entered combat' : 'Left combat'),
            self::GAP_REASON => (string) __(ucfirst($key)),
            self::DEATH_CAUSE => ucfirst($key),
            default => $key,
        };
    }

    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->slug() === $slug) {
                return $case;
            }
        }

        return null;
    }
}
