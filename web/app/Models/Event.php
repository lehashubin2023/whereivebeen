<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'sequence', 'event_type_id', 'payload'])]
#[WithoutTimestamps]
class Event extends Model
{
    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',    
            'sequence' => 'integer',
            'event_type_id' => 'integer',
            'payload' => 'json',
        ];
    }
}
