<?php

namespace App\Actions\GameSession\Import;

use App\Enums\GameSession\ImportBatchStateEnum;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportBatch;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BuildImportBatchList
{
    public const PAGE_NAME = 'files_page';

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function exec(User $user): LengthAwarePaginator
    {
        $batches = ImportBatch::query()
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(
                perPage: (int) config('pagination.per_page'),
                pageName: self::PAGE_NAME,
            )
            ->withQueryString();

        $progress = $this->progress(collect($batches->items())->pluck('id')->all());

        /** @var LengthAwarePaginator<int, array<string, mixed>> $rows */
        $rows = $batches->through(function (ImportBatch $batch) use ($progress) {
            $counts = $progress[$batch->id] ?? ['finished' => 0, 'failed' => 0];

            $state = ImportBatchStateEnum::resolve(
                $batch->status,
                (int) $batch->sessions_queued,
                $counts['finished'],
            );

            return [
                'id' => $batch->id,
                'filename' => $batch->filename,
                'file_size' => $batch->file_size,
                'state' => $state->value,
                'settled' => $state->isSettled(),
                'sessions_found' => $batch->sessions_found,
                'sessions_queued' => $batch->sessions_queued,
                'sessions_skipped' => $batch->sessions_skipped,
                'sessions_finished' => $counts['finished'],
                'sessions_failed' => $counts['failed'],
                'skipped' => $batch->skipped,
                'error_code' => $batch->error_code,
                'error_context' => $batch->error_context,
                'created_at' => $batch->created_at?->toIso8601String(),
            ];
        });

        return $rows;
    }

    /**
     * @param  array<int, int>  $ids
     * @return array<int, array{finished: int, failed: int}>
     */
    private function progress(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var Collection<int, object{import_batch_id: int, status: string, total: int}> $rows */
        $rows = ImportLog::query()
            ->whereIn('import_batch_id', $ids)
            ->whereIn('status', [ImportStatusEnum::COMPLETED->value, ImportStatusEnum::FAILED->value])
            ->selectRaw('import_batch_id, status, count(*) as total')
            ->groupBy('import_batch_id', 'status')
            ->toBase()
            ->get();

        $progress = [];

        foreach ($rows as $row) {
            $batchId = (int) $row->import_batch_id;
            $total = (int) $row->total;

            $progress[$batchId]['finished'] = ($progress[$batchId]['finished'] ?? 0) + $total;
            $progress[$batchId]['failed'] = ($progress[$batchId]['failed'] ?? 0)
                + ($row->status === ImportStatusEnum::FAILED->value ? $total : 0);
        }

        return $progress;
    }
}
