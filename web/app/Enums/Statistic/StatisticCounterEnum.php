<?php

namespace App\Enums\Statistic;

enum StatisticCounterEnum: int
{
    case COUNT = 1;
    case QUANTITY = 2;
    case DROPS = 3;
    case ACCEPT = 4;
    case TURNIN = 5;
    case JOINED = 6;
    case LEFT = 7;
    case SECONDS = 8;

    public function slug(): string
    {
        return match ($this) {
            self::COUNT => 'count',
            self::QUANTITY => 'quantity',
            self::DROPS => 'drops',
            self::ACCEPT => 'accept',
            self::TURNIN => 'turnin',
            self::JOINED => 'joined',
            self::LEFT => 'left',
            self::SECONDS => 'seconds',
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
