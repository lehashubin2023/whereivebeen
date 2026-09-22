<?php

namespace App\Jobs;

use App\Actions\GameSession\Import\ParseSavedVariablesFile;
use App\Actions\GameSession\MapImportFailure;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Queue('import-file')]
class ImportSavedVariablesFileJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public string $disk,
        public string $path,
        public User $user,
        public int $importBatchId,
    ) {}

    public function handle(ParseSavedVariablesFile $parser, MapImportFailure $failures): void
    {
        $batch = ImportBatch::query()->find($this->importBatchId);

        if ($batch === null) {
            return;
        }

        $startedAt = microtime(true);

        try {
            $parser->exec(Storage::disk($this->disk)->path($this->path), $this->user, $batch);
        } catch (Throwable $e) {
            $failure = $failures->exec($e);

            $parser->purge($batch);

            $batch->update([
                'status' => ImportBatchStatusEnum::FAILED,
                'error_code' => $failure['code'],
                'error_context' => $failure['context'],
            ]);
        } finally {
            Storage::disk($this->disk)->delete($this->path);

            $batch->update(['execution_time' => round(microtime(true) - $startedAt, 2)]);
        }
    }

    public function failed(Throwable $e): void
    {
        Storage::disk($this->disk)->delete($this->path);

        ImportBatch::query()
            ->where('id', $this->importBatchId)
            ->update(['status' => ImportBatchStatusEnum::FAILED]);
    }
}
