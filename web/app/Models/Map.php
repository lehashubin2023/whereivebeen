<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
#[WithoutTimestamps]
class Map extends Model
{
    const IMAGES_DIR = 'maps';

    protected $appends = ['image_path'];

    protected function imagePath(): Attribute
    {
        return Attribute::get(fn () => sprintf(
            '/%s/%s.png',
            self::IMAGES_DIR,
            str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]+/', '', $this->name))
        ));
    }
}
