<?php

namespace App\Models;

use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'character_id', 'game_session_id', 'session_start_at', 'import_status', 'import_error_message', 'version'])]
class GameSession extends Model
{
    const MAX_IMPORT_SIZE = 20971520; // 20mb

    public $casts = [
        'session_start_at' => 'datetime',
        'import_status' => ImportStatusEnum::class,
    ];
}
