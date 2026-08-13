<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'sequence', 'event_type_id', 'payload'])]
#[WithoutTimestamps]
class Event extends Model
{
    //
}
