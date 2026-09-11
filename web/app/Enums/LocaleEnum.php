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

    public function tag(): string
    {
        return match ($this) {
            self::EN => 'en_US',
            self::RU => 'ru_RU',
        };
    }

    public static function default(): self
    {
        return self::tryFrom(config()->string('app.fallback_locale')) ?? self::EN;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $locale): string => $locale->value, self::cases());
    }

    public static function pattern(): string
    {
        return implode('|', self::values());
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
