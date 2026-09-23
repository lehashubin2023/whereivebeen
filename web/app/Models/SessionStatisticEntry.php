<?php

namespace App\Models;

use App\Enums\Statistic\StatisticBucketEnum;
use App\Enums\Statistic\StatisticCounterEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['game_session_id', 'user_id', 'bucket', 'counter', 'entry_key', 'value', 'meta'])]
#[WithoutTimestamps]
class SessionStatisticEntry extends Model
{
    protected function casts(): array
    {
        return [
            'game_session_id' => 'integer',
            'user_id' => 'integer',
            'bucket' => StatisticBucketEnum::class,
            'counter' => StatisticCounterEnum::class,
            'value' => 'integer',
            'meta' => 'json',
        ];
    }
}
