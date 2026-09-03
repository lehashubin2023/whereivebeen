<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'character', 'game_session_id', 'session_start_at', 'import_status', 'import_error_message', 'version', 'realm', 'execution_time'])]
class GameSession extends Model
{
    use HasFactory;

    const MAX_IMPORT_SIZE = 20971520; // 20mb

    public $casts = [
        'session_start_at' => 'datetime',
    ];

    public function wayPoints(): HasMany
    {
        return $this->hasMany(WayPoint::class, 'game_session_id');
    }
}
