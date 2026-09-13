<?php

namespace App\Enums\GameSession;

enum SavedVariablesSkipReasonEnum: string
{
    case NO_POINTS = 'no_points';
    case NO_CHARACTER = 'no_character';
    case NO_SESSION_ID = 'no_session_id';
    case NO_START_TIME = 'no_start_time';
    case TOO_LARGE = 'too_large';

    public function label(): string
    {
        return match ($this) {
            self::NO_POINTS => __('No recorded points'),
            self::NO_CHARACTER => __('No character name'),
            self::NO_SESSION_ID => __('No session id'),
            self::NO_START_TIME => __('No start time'),
            self::TOO_LARGE => __('Too large to import'),
        };
    }
}
