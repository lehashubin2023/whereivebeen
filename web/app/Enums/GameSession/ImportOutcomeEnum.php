<?php

namespace App\Enums\GameSession;

enum ImportOutcomeEnum: string
{
    case CREATED = 'created';
    case REPLACED = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => __('Added'),
            self::REPLACED => __('Replaced'),
        };
    }
}
