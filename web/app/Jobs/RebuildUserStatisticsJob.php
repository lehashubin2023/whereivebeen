<?php

namespace App\Jobs;

use App\Actions\Statistic\Rebuild\RebuildUserStatistics;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

#[Queue('statistics')]
class RebuildUserStatisticsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 600;

    public function __construct(public User $user) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    public function handle(RebuildUserStatistics $rebuild): void
    {
        $rebuild->exec($this->user);
    }
}
