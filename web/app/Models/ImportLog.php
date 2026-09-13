<?php

namespace App\Models;

use App\Enums\GameSession\ImportOutcomeEnum;
use App\Enums\GameSession\ImportStatusEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'game_session_id', 'import_batch_id', 'status', 'outcome', 'points_total', 'points_done', 'execution_time', 'error_code', 'error_context', 'warnings', 'error_message'])]
class ImportLog extends Model
{
    public $casts = [
        'status' => ImportStatusEnum::class,
        'outcome' => ImportOutcomeEnum::class,
        'error_context' => 'array',
        'warnings' => 'array',
    ];

    public function getConnectionName(): ?string
    {
        $connection = config('database.import_log_connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * @return BelongsTo<ImportBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }
}
