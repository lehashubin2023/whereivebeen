<?php

namespace App\Support\GameSession\ImportProgress;

use App\DTOs\GameSession\CreateImportLogDTO;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportLog;
use App\Models\User;
use Throwable;

class ImportGameSessionProgress implements ImportGameSessionProgressContract
{
    private ImportLog $log;

    private float $startedAt;

    public function __construct(User $user)
    {
        $this->startedAt = microtime(true);
        $this->log = ImportLog::create(
            CreateImportLogDTO::fromArray(['user_id' => $user->id])->toArray()
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

    public function fail(Throwable $e): void
    {
        $this->log->update([
            'status' => ImportStatusEnum::FAILED,
            'error_message' => $e->getMessage(),
            'execution_time' => $this->elapsed(),
        ]);
    }

    public function id(): int
    {
        return $this->log->id;
    }

    private function elapsed(): float
    {
        return round(microtime(true) - $this->startedAt, 2);
    }
}
