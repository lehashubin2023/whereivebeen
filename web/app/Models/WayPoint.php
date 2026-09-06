<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'map_id', 'sequence', 'time', 'x', 'y'])]
#[WithoutTimestamps]
class WayPoint extends Model
{
    public const COODS_FIELD_LENGTH = 65535;

    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',
            'map_id' => 'integer',
            'sequence' => 'integer',
        ];
    }

    protected function x(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => (float) $value / self::COODS_FIELD_LENGTH,
            set: fn (float $value) => (int) ($value * self::COODS_FIELD_LENGTH),
        );
    }

    protected function y(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => (float) $value / self::COODS_FIELD_LENGTH,
            set: fn (float $value) => (int) ($value * self::COODS_FIELD_LENGTH),
        );
    }
}
