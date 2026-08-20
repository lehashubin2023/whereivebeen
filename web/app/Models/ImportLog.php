<?php

namespace App\Models;

use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'status', 'points_total', 'points_done', 'execution_time', 'error_message'])]
class ImportLog extends Model
{
    public $casts = [
        'status' => ImportStatusEnum::class,
    ];
}
