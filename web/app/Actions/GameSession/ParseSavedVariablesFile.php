<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\SavedVariablesSessionDTO;
use App\Enums\GameSession\ImportBatchStatusEnum;
use App\Jobs\ImportGameSessionJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\GameSession\ImportSource\SpooledImportSource;
use App\Support\GameSession\SavedVariables\SavedVariablesReader;
use App\Support\GameSession\SavedVariables\SessionSpool;
use App\Support\Lua\LuaParseLimits;
use Illuminate\Support\Facades\Storage;

/**
 * Разбирает файл SavedVariables и ставит в очередь по джобе на каждую пригодную
 * сессию: так у каждой появляется собственная строка журнала и собственный
 * прогресс, а разбор файла не упирается в таймаут одной большой задачи.
 */
class ParseSavedVariablesFile
{
    private const MAX_REPORTED_SKIPS = 50;

    public function __construct(
        private readonly SavedVariablesReader $reader,
        private readonly NormalizeSavedVariablesSession $normalizer,
        private readonly SessionSpool $spool,
    ) {}

    public function exec(
        string $absolutePath,
        User $user,
        ImportBatch $batch,
        LuaParseLimits $limits = new LuaParseLimits,
    ): void {
        $batch->update(['status' => ImportBatchStatusEnum::PARSING]);

        $uuid = $this->batchUuid($batch);
        $queued = 0;
        $skipped = [];

        $found = $this->reader->read(
            $absolutePath,
            $uuid,
            $limits,
            function (SavedVariablesSessionDTO $session) use ($user, $batch, $uuid, &$queued, &$skipped) {
                $reason = $this->normalizer->reject($session);

                if ($reason !== null) {
                    if (count($skipped) < self::MAX_REPORTED_SKIPS) {
                        $skipped[] = [
                            'session_id' => (string) $session->sessionId,
                            'character' => $session->character(),
                            'points' => $session->pointsCount,
                            'reason' => $reason->value,
                        ];
                    }

                    Storage::disk(SessionSpool::DISK)->delete($session->spoolPath);

                    return;
                }

                $export = $this->normalizer->exec($session, $uuid);

                Storage::disk(SessionSpool::DISK)->delete($session->spoolPath);

                ImportGameSessionJob::dispatch(
                    new SpooledImportSource(SessionSpool::DISK, $export),
                    $user,
                    $batch->id,
                );

                $queued++;
            },
        );

        $batch->update([
            'status' => ImportBatchStatusEnum::DISPATCHED,
            'sessions_found' => $found,
            'sessions_queued' => $queued,
            'sessions_skipped' => count($skipped),
            'skipped' => $skipped,
        ]);
    }

    public function purge(ImportBatch $batch): void
    {
        $this->spool->purge($this->batchUuid($batch));
    }

    private function batchUuid(ImportBatch $batch): string
    {
        return 'batch-'.$batch->id;
    }
}
