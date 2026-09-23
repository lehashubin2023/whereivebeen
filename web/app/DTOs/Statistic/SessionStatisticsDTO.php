<?php

namespace App\DTOs\Statistic;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class SessionStatisticsDTO implements DTOContract
{
    /**
     * @param  array<int, int>  $eventCounts
     * @param  array<int, array{seconds: int, points: int, deaths: int}>  $maps
     * @param  array<int, StatisticEntryDTO>  $entries
     */
    public function __construct(
        public readonly int $durationSeconds,
        public readonly int $pointsCount,
        public readonly array $eventCounts,
        public readonly array $maps,
        public readonly array $entries,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            durationSeconds: (int) ($data['duration_seconds'] ?? 0),
            pointsCount: (int) ($data['points_count'] ?? 0),
            eventCounts: (array) ($data['event_counts'] ?? []),
            maps: (array) ($data['maps'] ?? []),
            entries: (array) ($data['entries'] ?? []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'duration_seconds' => $this->durationSeconds,
            'points_count' => $this->pointsCount,
            'event_counts' => $this->eventCounts,
            'maps' => $this->maps,
            'entries' => array_map(fn (StatisticEntryDTO $entry) => $entry->toArray(), $this->entries),
        ];
    }
}
