<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{WithoutTimestamps, Fillable};
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['game_session_id', 'map_id', 'sequence', 'time', 'x', 'y'])]
#[WithoutTimestamps]
class WayPoint extends Model
{
    public const COODS_FIELD_LENGTH = 65535;

    public function event(): HasOne
    {
        return $this->hasOne(Event::class, 'sequence', 'sequence');
    }

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
            set: fn (string $value) => (int) $value * self::COODS_FIELD_LENGTH,
        );
    }

    protected function y(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => (float) $value / self::COODS_FIELD_LENGTH,
            set: fn (string $value) => (int) $value * self::COODS_FIELD_LENGTH,
        );
    }
}
