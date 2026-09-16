<?php

namespace App\Enums\GameSession;

enum ImportBatchStateEnum: string
{
    case QUEUED = 'queued';
    case PARSING = 'parsing';
    case IMPORTING = 'importing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    public static function resolve(ImportBatchStatusEnum $status, int $queued, int $finished): self
    {
        return match ($status) {
            ImportBatchStatusEnum::NEW => self::QUEUED,
            ImportBatchStatusEnum::PARSING => self::PARSING,
            ImportBatchStatusEnum::FAILED => self::FAILED,
            ImportBatchStatusEnum::DISPATCHED => $finished >= $queued
                ? self::COMPLETED
                : self::IMPORTING,
        };
    }

    public function label(): string
    {
        return (string) __(match ($this) {
            self::QUEUED => 'Queued',
            self::PARSING => 'Reading file',
            self::IMPORTING => 'Importing sessions',
            self::COMPLETED => 'Import finished',
            self::FAILED => 'Failed',
        });
    }

    public function isSettled(): bool
    {
        return $this === self::COMPLETED || $this === self::FAILED;
    }
}
