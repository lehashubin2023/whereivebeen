<?php

namespace App\Enums\GameSession;

enum EventTypeEnum: int
{
    case MOUNT = 1;
    case COMBAT = 2;
    case DEATH = 3;
    case RESURRECT = 4;
    case LEVELUP = 5;
    case LOOT = 6;
    case VISIT = 7;
    case GROUP = 8;
    case QUEST = 9;
    case TAXI = 10;
    case GAP = 11;
    case ZONE = 12;
    case GATHER = 13;

    public function slug(): string
    {
        return match ($this) {
            self::MOUNT => 'mount',
            self::COMBAT => 'combat',
            self::DEATH => 'death',
            self::RESURRECT => 'resurrect',
            self::LEVELUP => 'levelup',
            self::LOOT => 'loot',
            self::VISIT => 'visit',
            self::GROUP => 'group',
            self::QUEST => 'quest',
            self::TAXI => 'taxi',
            self::GAP => 'gap',
            self::ZONE => 'zone',
            self::GATHER => 'gather',
        };
    }

    public function label(): string
    {
        return (string) __(match ($this) {
            self::MOUNT => 'Mount',
            self::COMBAT => 'Combat',
            self::DEATH => 'Death',
            self::RESURRECT => 'Resurrect',
            self::LEVELUP => 'Level up',
            self::LOOT => 'Loot',
            self::VISIT => 'Visit',
            self::GROUP => 'Group',
            self::QUEST => 'Quest',
            self::TAXI => 'Taxi',
            self::GAP => 'Route gap',
            self::ZONE => 'Zone',
            self::GATHER => 'Gather',
        });
    }

    public function hasMarker(): bool
    {
        return match ($this) {
            self::GAP, self::COMBAT => false,
            default => true,
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
