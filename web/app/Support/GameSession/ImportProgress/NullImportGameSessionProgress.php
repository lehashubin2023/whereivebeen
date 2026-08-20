<?php

namespace App\Support\GameSession\ImportProgress;

use Throwable;

/**
 * No-op реализация: для прямых вызовов экшенов и тестов, где лог не нужен.
 */
class NullImportGameSessionProgress implements ImportGameSessionProgressContract
{
    public function process(int $total): void {}

    public function track(int $done): void {}

    public function complete(int $gameSessionId): void {}

    public function fail(Throwable $e): void {}
}
