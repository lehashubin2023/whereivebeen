<?php

namespace App\Enums\GameSession;

enum ImportBatchStatusEnum: string
{
    case NEW = 'new';
    case PARSING = 'parsing';
    case DISPATCHED = 'dispatched';
    case FAILED = 'failed';
}
