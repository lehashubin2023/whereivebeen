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

    public const MAX_ID = 65535;

    protected $appends = ['image_path'];

    private ?bool $imageExists = null;

    protected function casts(): array
    {
        return [
            'auto_added' => 'boolean',
        ];
    }

    protected function imagePath(): Attribute
    {
        return Attribute::get(fn () => sprintf(
            '/%s/%s.png',
            self::IMAGES_DIR,
            str_replace(' ', '_', preg_replace('/[^A-Za-z0-9 ]+/', '', $this->name))
        ));
    }

    public function hasImage(): bool
    {
        if ($this->imageExists === null) {
            $this->imageExists = file_exists(
                public_path(ltrim((string) $this->getAttribute('image_path'), '/'))
            );
        }

        return $this->imageExists;
    }

    public function isAutoAdded(): bool
    {
        return (bool) $this->getAttribute('auto_added');
    }
}
