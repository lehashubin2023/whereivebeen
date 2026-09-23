<?php

namespace App\Console\Commands;

use App\Actions\Statistic\CollectStoredSessionStatistics;
use App\Actions\Statistic\Rebuild\RebuildUserStatistics;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RebuildStatisticsCommand extends Command
{
    protected $signature = 'statistics:rebuild
        {--user= : Rebuild a single user}';

    protected $description = 'Recollect session statistics from the stored waypoints and rebuild the user snapshots';

    public function handle(CollectStoredSessionStatistics $collect, RebuildUserStatistics $rebuild): int
    {
        $userId = $this->option('user');

        $query = GameSession::query()
            ->whereNotNull('user_id')
            ->when($userId !== null, fn ($builder) => $builder->where('user_id', (int) $userId));

        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info('No sessions to rebuild.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $userIds = [];

        $query->orderBy('id')->chunkById(100, function (Collection $sessions) use ($collect, $bar, &$userIds) {
            foreach ($sessions as $session) {
                $collect->exec($session);

                $userIds[(int) $session->getAttribute('user_id')] = true;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        User::query()->whereIn('id', array_keys($userIds))->each(
            fn (User $user) => $rebuild->exec($user)
        );

        $this->info(sprintf('Rebuilt sessions: %d, users: %d', $total, count($userIds)));

        return self::SUCCESS;
    }
}
