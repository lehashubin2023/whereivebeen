<?php

namespace App\Jobs;

use App\Actions\GameSession\ImportGameSession;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

#[Queue('import')]
class ImportGameSessionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $rawGameSessionInput,
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImportGameSession $importer): void
    {
        $progress = new ImportGameSessionProgress($this->user);

        try {
            $gameSessionId = $importer->exec($this->rawGameSessionInput, $this->user, $progress);
            $progress->complete($gameSessionId);
        } catch (\Throwable $e) {
            $progress->fail($e);
        }
    }
}
