<?php

namespace App\Jobs;

use App\Actions\GameSession\ImportGameSession;
use App\DTOs\GameSession\CreateImportLogDTO;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportLog;
use App\Models\User;
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
    public function handle(
        ImportGameSession $importer
    ): void
    {
        try {
            $importLog = ImportLog::create(CreateImportLogDTO::fromArray([])->toArray());
            $importer->exec($importLog, $this->rawGameSessionInput, $this->user);
        } catch (\Throwable $e) {
            $importLog->update([
                'status' => ImportStatusEnum::FAILED,
                'status_message' => $e->getMessage(),
                'execution_time' => strtotime($importLog->created_at) - time(),
            ]);
        }
    }
}