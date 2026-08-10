<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'image_path'])]
#[WithoutTimestamps]
class Map extends Model
{
    //
}
