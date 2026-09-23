<?php

namespace App\Console\Commands;

use App\Actions\Statistic\CollectStoredSessionStatistics;
use App\Models\GameSession;
use Illuminate\Console\Command;

class RebuildStatisticsCommand extends Command
{
    protected $signature = 'statistics:rebuild
        {--user= : Rebuild the sessions of a single user}';

    protected $description = 'Recollect session statistics from the stored waypoints and events';

    public function handle(CollectStoredSessionStatistics $collect): int
    {
        $userId = $this->option('user');

        $query = GameSession::query()
            ->when($userId !== null, fn ($builder) => $builder->where('user_id', (int) $userId));

        $total = $query->clone()->count();

        if ($total === 0) {
            $this->info('No sessions to rebuild.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);

        $query->orderBy('id')->chunkById(100, function ($sessions) use ($collect, $bar) {
            foreach ($sessions as $session) {
                $collect->exec($session);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info(sprintf('Rebuilt sessions: %d', $total));

        return self::SUCCESS;
    }
}
