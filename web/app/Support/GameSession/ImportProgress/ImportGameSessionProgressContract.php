<?php

namespace App\Support\GameSession\ImportProgress;

use App\Enums\GameSession\ImportOutcomeEnum;
use Throwable;

interface ImportGameSessionProgressContract
{
    public function process(int $total): void;

    public function track(int $done): void;

    public function complete(int $gameSessionId): void;

    public function outcome(ImportOutcomeEnum $outcome): void;

    /**
     * @param  array{code: string, context: array<string, mixed>}|null  $failure
     */
    public function fail(Throwable $e, ?array $failure = null): void;

    /**
     * @param  array<string, mixed>  $warnings
     */
    public function warn(array $warnings): void;
}
