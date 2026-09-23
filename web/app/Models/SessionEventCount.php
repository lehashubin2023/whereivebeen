<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'user_id', 'event_type_id', 'total'])]
#[WithoutTimestamps]
class SessionEventCount extends Model
{
    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',
            'user_id' => 'integer',
            'event_type_id' => 'integer',
            'total' => 'integer',
        ];
    }
}
