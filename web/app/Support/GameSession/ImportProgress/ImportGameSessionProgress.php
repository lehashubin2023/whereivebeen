<?php

namespace App\Support\GameSession\ImportProgress;

use App\DTOs\GameSession\CreateImportLogDTO;
use App\Enums\GameSession\ImportOutcomeEnum;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportLog;
use App\Models\User;
use Throwable;

class ImportGameSessionProgress implements ImportGameSessionProgressContract
{
    private const MAX_EXECUTION_TIME = 999999.99;

    private ImportLog $log;

    private float $startedAt;

    public function __construct(User $user, ?int $importBatchId = null)
    {
        $this->startedAt = microtime(true);
        $this->log = ImportLog::create(
            CreateImportLogDTO::fromArray([
                'user_id' => $user->id,
                'import_batch_id' => $importBatchId,
            ])->toArray()
        );
    }

    public function process(int $total): void
    {
        $this->log->update([
            'status' => ImportStatusEnum::IN_PROCESS,
            'points_total' => $total,
        ]);
    }

    public function track(int $done): void
    {
        $this->log->update(['points_done' => $done]);
    }

    public function complete(int $gameSessionId): void
    {
        $this->log->update([
            'game_session_id' => $gameSessionId,
            'status' => ImportStatusEnum::COMPLETED,
            'execution_time' => $this->elapsed(),
        ]);
    }

    public function outcome(ImportOutcomeEnum $outcome): void
    {
        $this->log->update(['outcome' => $outcome]);
    }

    public function fail(Throwable $e, ?array $failure = null): void
    {
        $this->log->update([
            'status' => ImportStatusEnum::FAILED,
            'error_code' => $failure['code'] ?? null,
            'error_context' => $failure['context'] ?? null,
            'error_message' => $e->getMessage(),
            'execution_time' => $this->elapsed(),
        ]);
    }

    public function warn(array $warnings): void
    {
        $this->log->update(['warnings' => $warnings]);
    }

    public function id(): int
    {
        return $this->log->id;
    }

    private function elapsed(): float
    {
        return min(self::MAX_EXECUTION_TIME, round(microtime(true) - $this->startedAt, 2));
    }
}
