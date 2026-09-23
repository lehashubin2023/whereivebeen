<?php

namespace App\Actions\Statistic;

use App\DTOs\Statistic\StatisticGroupDTO;
use App\DTOs\Statistic\StatisticTableDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum;
use App\Models\Map;
use App\Models\User;
use App\Models\UserStatistic;
use App\Support\Statistic\TableCatalog;
use App\Support\Statistic\TableColumn;
use App\Support\Statistic\TableDefinition;

class ReadUserStatistics
{
    public function __construct(private readonly TableCatalog $catalog) {}

    /**
     * @return array<string, mixed>
     */
    public function exec(User $user): array
    {
        $statistics = UserStatistic::query()->find($user->id);

        return [
            'overview' => $this->overview($statistics),
            'groups' => $this->groups($statistics),
            'journey' => $this->journey($statistics),
            'stale' => $statistics !== null && $statistics->is_stale,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    private function overview(?UserStatistic $statistics): array
    {
        return [
            ['label' => (string) __('Sessions'), 'value' => (string) (int) $statistics?->sessions_count],
            ['label' => (string) __('Waypoints'), 'value' => (string) (int) $statistics?->points_count],
            ['label' => (string) __('Zones visited'), 'value' => (string) (int) $statistics?->zones_count],
            ['label' => (string) __('Events'), 'value' => (string) (int) $statistics?->events_count],
            ['label' => (string) __('Time played'), 'value' => self::duration((int) $statistics?->seconds_played)],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groups(?UserStatistic $statistics): array
    {
        $groups = [];

        foreach (self::listOf($statistics?->getAttribute('groups')) as $group) {
            $type = EventTypeEnum::fromSlug((string) ($group['event'] ?? ''));

            if ($type === null) {
                continue;
            }

            $groups[] = StatisticGroupDTO::fromArray([
                'slug' => $type->slug(),
                'label' => $type->label(),
                'total' => $group['total'] ?? 0,
                'tables' => $this->tables(self::listOf($group['tables'] ?? null)),
            ])->toArray();
        }

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function listOf(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $row) => is_array($row) ? $row : [],
            $value,
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tables
     * @return array<int, array<string, mixed>>
     */
    private function tables(array $tables): array
    {
        $rendered = [];

        foreach ($tables as $table) {
            $bucket = StatisticBucketEnum::fromSlug((string) ($table['bucket'] ?? ''));
            $definition = $bucket === null ? null : $this->catalog->find($bucket);

            if ($definition !== null) {
                $rendered[] = $this->table($definition, $table);
            }
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>  $table
     * @return array<string, mixed>
     */
    private function table(TableDefinition $definition, array $table): array
    {
        $columns = [(string) __($definition->keyColumn)];

        if ($definition->metaColumn !== null) {
            $columns[] = (string) __($definition->metaColumn);
        }

        foreach ($definition->columns as $column) {
            $columns[] = (string) __($column->label);
        }

        $rows = [];

        foreach (self::listOf($table['rows'] ?? null) as $row) {
            $rows[] = $this->row($definition, $row);
        }

        return StatisticTableDTO::fromArray([
            'title' => (string) __($definition->title),
            'columns' => $columns,
            'rows' => $rows,
            'rows_total' => $table['rows_total'] ?? count($rows),
        ])->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function row(TableDefinition $definition, array $row): array
    {
        $counters = (array) ($row['counters'] ?? []);
        $cells = [$definition->bucket->keyLabel((string) ($row['key'] ?? ''))];

        if ($definition->metaColumn !== null) {
            $cells[] = $this->metaLabel($row['meta'] ?? null);
        }

        foreach ($definition->columns as $column) {
            $cells[] = $this->cell($column, (int) ($counters[$column->counter->slug()] ?? 0));
        }

        return $cells;
    }

    private function cell(TableColumn $column, int $value): string
    {
        return $column->duration ? self::duration($value) : (string) $value;
    }

    private function metaLabel(mixed $meta): string
    {
        if (! is_array($meta) || $meta === []) {
            return '—';
        }

        $pvp = (bool) ($meta['pvp'] ?? false);

        if (isset($meta['class'])) {
            return ucfirst(strtolower((string) $meta['class'])).($pvp ? ' (player)' : '');
        }

        if (isset($meta['creature_type'])) {
            return (string) $meta['creature_type'];
        }

        return $pvp ? (string) __('Player') : '—';
    }

    /**
     * @return array<string, mixed>
     */
    private function journey(?UserStatistic $statistics): array
    {
        $raw = $statistics?->getAttribute('journey');
        $journey = is_array($raw) ? $raw : [];
        $zones = self::listOf($journey['zones'] ?? null);
        $deadliest = $journey['deadliest'] ?? null;
        $deadliest = is_array($deadliest) ? $deadliest : null;

        $names = $this->mapNames($zones, $deadliest);

        return [
            'activity' => self::listOf($journey['activity'] ?? null),
            'zones' => $this->zones($zones, $names),
            'levels' => self::listOf($journey['levels'] ?? null),
            'deadliest' => $this->deadliest($deadliest, $names),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $zones
     * @param  array<int, string>  $names
     * @return array<int, array{name: string, seconds: int, points: int}>
     */
    private function zones(array $zones, array $names): array
    {
        $rendered = [];

        foreach ($zones as $zone) {
            $name = $names[(int) ($zone['map_id'] ?? 0)] ?? '';

            if ($name === '') {
                continue;
            }

            $rendered[] = [
                'name' => $name,
                'seconds' => (int) ($zone['seconds'] ?? 0),
                'points' => (int) ($zone['points'] ?? 0),
            ];
        }

        return $rendered;
    }

    /**
     * @param  array<string, mixed>|null  $deadliest
     * @param  array<int, string>  $names
     * @return array{name: string, deaths: int}|null
     */
    private function deadliest(?array $deadliest, array $names): ?array
    {
        if ($deadliest === null) {
            return null;
        }

        $name = $names[(int) ($deadliest['map_id'] ?? 0)] ?? '';

        return $name === '' ? null : ['name' => $name, 'deaths' => (int) ($deadliest['deaths'] ?? 0)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $zones
     * @param  array<string, mixed>|null  $deadliest
     * @return array<int, string>
     */
    private function mapNames(array $zones, ?array $deadliest): array
    {
        $ids = array_map(fn (array $zone) => (int) ($zone['map_id'] ?? 0), $zones);

        if ($deadliest !== null) {
            $ids[] = (int) ($deadliest['map_id'] ?? 0);
        }

        $ids = array_values(array_filter(array_unique($ids)));

        if ($ids === []) {
            return [];
        }

        /** @var array<int, string> $names */
        $names = Map::query()->whereIn('id', $ids)->pluck('name', 'id')->all();

        return $names;
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.__('s');
        }

        $minutes = (int) round($seconds / 60);

        if ($minutes < 60) {
            return $minutes.__('m');
        }

        return intdiv($minutes, 60).__('h').' '.($minutes % 60).__('m');
    }
}
