<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'character', 'game_session_id', 'session_start_at', 'session_end_at', 'continues_from', 'import_status', 'import_error_message', 'version', 'game_version', 'addon_version', 'schema_version', 'locale', 'faction', 'class', 'level', 'realm', 'execution_time'])]
class GameSession extends Model
{
    use HasFactory;

    const MAX_IMPORT_SIZE = 20971520;

    public $casts = [
        'session_start_at' => 'datetime',
        'session_end_at' => 'datetime',
        'continues_from' => 'integer',
        'schema_version' => 'integer',
        'level' => 'integer',
    ];

    public function wayPoints(): HasMany
    {
        return $this->hasMany(WayPoint::class, 'game_session_id');
    }
}
