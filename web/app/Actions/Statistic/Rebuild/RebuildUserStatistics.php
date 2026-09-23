<?php

namespace App\Actions\Statistic\Rebuild;

use App\Models\User;
use App\Models\UserStatistic;

class RebuildUserStatistics
{
    public function __construct(
        private readonly AggregateUserOverview $overview,
        private readonly AggregateUserCounters $counters,
        private readonly AggregateUserJourney $journey,
    ) {}

    public function exec(User $user): UserStatistic
    {
        $userId = (int) $user->id;

        return UserStatistic::query()->updateOrCreate(['user_id' => $userId], [
            ...$this->overview->exec($userId),
            'groups' => $this->counters->exec($userId),
            'journey' => $this->journey->exec($userId),
            'is_stale' => false,
            'calculated_at' => now(),
        ]);
    }
}
