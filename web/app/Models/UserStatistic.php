<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'sessions_count', 'points_count', 'events_count', 'zones_count',
    'seconds_played', 'groups', 'journey', 'is_stale', 'calculated_at',
])]
class UserStatistic extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'sessions_count' => 'integer',
            'points_count' => 'integer',
            'events_count' => 'integer',
            'zones_count' => 'integer',
            'seconds_played' => 'integer',
            'groups' => 'array',
            'journey' => 'array',
            'is_stale' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }
}
