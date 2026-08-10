<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'character_id', 'game_session_id', 'session_start_at', 'import_status', 'version'])]
class GameSession extends Model
{
    //
}
