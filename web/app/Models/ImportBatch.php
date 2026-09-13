<?php

namespace App\Models;

use App\Enums\GameSession\ImportBatchStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'filename', 'file_size', 'status', 'sessions_found', 'sessions_queued', 'sessions_skipped', 'skipped', 'error_code', 'error_context', 'execution_time'])]
class ImportBatch extends Model
{
    public $casts = [
        'status' => ImportBatchStatusEnum::class,
        'skipped' => 'array',
        'error_context' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        $connection = config('database.import_log_connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * @return HasMany<ImportLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ImportLog::class, 'import_batch_id');
    }
}
