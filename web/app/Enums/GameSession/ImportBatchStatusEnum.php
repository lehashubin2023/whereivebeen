<?php

namespace App\Enums\GameSession;

enum ImportBatchStatusEnum: string
{
    case NEW = 'new';
    case PARSING = 'parsing';
    case DISPATCHED = 'dispatched';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('Queued'),
            self::PARSING => __('Reading file'),
            self::DISPATCHED => __('Sessions queued'),
            self::FAILED => __('Failed'),
        };
    }
}
