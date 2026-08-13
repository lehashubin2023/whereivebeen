<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'map_id', 'sequence', 'time', 'x', 'y', 'state'])]
#[WithoutTimestamps]
class WayPoint extends Model
{
    //
}
