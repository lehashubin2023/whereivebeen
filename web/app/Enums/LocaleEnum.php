<?php

namespace App\Enums;

enum LocaleEnum: string
{
    case EN = 'en';
    case RU = 'ru';

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::RU => 'Русский',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $locale): array => [
                'value' => $locale->value,
                'label' => $locale->label(),
            ],
            self::cases(),
        );
    }
}
