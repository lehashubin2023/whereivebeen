<?php

namespace App\Actions\Statistic\Rebuild;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\SessionEventCount;
use App\Models\SessionStatisticEntry;
use App\Support\Statistic\TableCatalog;
use App\Support\Statistic\TableDefinition;

class AggregateUserCounters
{
    public function __construct(private readonly TableCatalog $catalog) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function exec(int $userId): array
    {
        $totals = SessionEventCount::query()
            ->where('user_id', $userId)
            ->selectRaw('event_type_id, sum(total) as total')
            ->groupBy('event_type_id')
            ->pluck('total', 'event_type_id')
            ->all();

        $buckets = $this->buckets($userId);
        $groups = [];

        foreach (EventTypeEnum::cases() as $type) {
            $total = (int) ($totals[$type->value] ?? 0);

            if ($total === 0) {
                continue;
            }

            $tables = [];

            foreach ($this->catalog->forEvent($type) as $definition) {
                $table = $this->table($definition, $buckets[$definition->bucket->value] ?? []);

                if ($table !== null) {
                    $tables[] = $table;
                }
            }

            $groups[] = ['event' => $type->slug(), 'total' => $total, 'tables' => $tables];
        }

        usort($groups, fn (array $first, array $second) => $second['total'] <=> $first['total']);

        return $groups;
    }

    /**
     * @return array<int, array<string, array{counters: array<int, int>, meta: array<string, mixed>|null}>>
     */
    private function buckets(int $userId): array
    {
        $buckets = [];

        SessionStatisticEntry::query()
            ->where('user_id', $userId)
            ->selectRaw('bucket, entry_key, counter, sum(value) as value, min(meta) as meta')
            ->groupBy('bucket', 'entry_key', 'counter')
            ->cursor()
            ->each(function (SessionStatisticEntry $row) use (&$buckets) {
                $bucket = (int) $row->getRawOriginal('bucket');
                $key = (string) $row->getAttribute('entry_key');

                $buckets[$bucket][$key]['counters'][(int) $row->getRawOriginal('counter')]
                    = (int) $row->getAttribute('value');
                $buckets[$bucket][$key]['meta'] ??= $this->meta($row->getAttribute('meta'));
            });

        return $buckets;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function meta(mixed $raw): ?array
    {
        if (is_array($raw)) {
            return $raw === [] ? null : $raw;
        }

        if (! is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /**
     * @param  array<string, array{counters: array<int, int>, meta: array<string, mixed>|null}>  $source
     * @return array<string, mixed>|null
     */
    private function table(TableDefinition $definition, array $source): ?array
    {
        if ($source === []) {
            return null;
        }

        $keys = array_map('strval', array_keys($source));

        usort($keys, function (string $first, string $second) use ($source, $definition): int {
            if ($definition->naturalKeySort) {
                return strnatcasecmp($first, $second);
            }

            $weight = fn (string $key): int => $definition->sortCounter === null
                ? array_sum($source[$key]['counters'])
                : ($source[$key]['counters'][$definition->sortCounter->value] ?? 0);

            return $weight($second) <=> $weight($first) ?: strnatcasecmp($first, $second);
        });

        $rows = [];

        foreach (array_slice($keys, 0, TableCatalog::MAX_ROWS) as $key) {
            $counters = [];

            foreach ($definition->columns as $column) {
                $counters[$column->counter->slug()] = $source[$key]['counters'][$column->counter->value] ?? 0;
            }

            $rows[] = [
                'key' => $key,
                'meta' => $source[$key]['meta'] ?? null,
                'counters' => $counters,
            ];
        }

        return [
            'bucket' => $definition->bucket->slug(),
            'rows_total' => count($keys),
            'rows' => $rows,
        ];
    }
}
