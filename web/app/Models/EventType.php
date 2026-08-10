<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
#[WithoutTimestamps]
class EventType extends Model
{
    //
}
