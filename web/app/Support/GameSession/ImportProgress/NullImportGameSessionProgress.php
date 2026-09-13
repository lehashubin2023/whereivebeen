<?php

namespace App\Support\GameSession\ImportProgress;

use App\Enums\GameSession\ImportOutcomeEnum;
use Throwable;

/**
 * No-op реализация: для прямых вызовов экшенов и тестов, где лог не нужен.
 */
class NullImportGameSessionProgress implements ImportGameSessionProgressContract
{
    public function process(int $total): void {}

    public function track(int $done): void {}

    public function complete(int $gameSessionId): void {}

    public function outcome(ImportOutcomeEnum $outcome): void {}

    public function fail(Throwable $e, ?array $failure = null): void {}

    public function warn(array $warnings): void {}
}
