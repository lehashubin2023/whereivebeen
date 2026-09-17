<?php

namespace App\Enums\GameSession;

enum ImportTabEnum: string
{
    case SESSIONS = 'sessions';
    case FILES = 'files';

    public static function resolve(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::SESSIONS;
    }
}
