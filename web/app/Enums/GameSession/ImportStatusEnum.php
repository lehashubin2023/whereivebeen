<?php

namespace App\Enums\GameSession;

enum ImportStatusEnum: string
{
    case NEW = 'new';
    case IN_PROCESS = 'in_process';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}