<?php

namespace App\Jobs;

use App\Actions\GameSession\Import\ImportGameSession;
use App\Actions\GameSession\MapImportFailure;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgress;
use App\Support\GameSession\ImportSource\ImportSourceContract;
use App\Support\GameSession\ImportSource\InlineImportSource;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

#[Queue('import')]
class ImportGameSessionJob implements ShouldQueue
{
    use Queueable;

    private const REBUILD_DELAY = 5;

    public function __construct(
        public string|ImportSourceContract $rawGameSessionInput,
        public User $user,
        public ?int $importBatchId = null
    ) {}

    public function handle(ImportGameSession $importer, MapImportFailure $failures): void
    {
        $source = $this->source();
        $progress = new ImportGameSessionProgress($this->user, $this->importBatchId);

        try {
            $gameSessionId = $importer->exec($source->read(), $this->user, $progress);
            $progress->complete($gameSessionId);

            RebuildUserStatisticsJob::dispatch($this->user)->delay(now()->addSeconds(self::REBUILD_DELAY));
        } catch (\Throwable $e) {
            $progress->fail($e, $failures->exec($e));
        } finally {
            $source->release();
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->source()->release();
    }

    private function source(): ImportSourceContract
    {
        return is_string($this->rawGameSessionInput)
            ? new InlineImportSource($this->rawGameSessionInput)
            : $this->rawGameSessionInput;
    }
}
