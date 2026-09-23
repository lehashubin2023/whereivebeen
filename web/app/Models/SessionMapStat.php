<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'user_id', 'map_id', 'seconds', 'points', 'deaths'])]
#[WithoutTimestamps]
class SessionMapStat extends Model
{
    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',
            'user_id' => 'integer',
            'map_id' => 'integer',
            'seconds' => 'integer',
            'points' => 'integer',
            'deaths' => 'integer',
        ];
    }
}
