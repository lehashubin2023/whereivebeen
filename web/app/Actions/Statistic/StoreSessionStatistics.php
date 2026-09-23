<?php

namespace App\Actions\Statistic;

use App\DTOs\Statistic\SessionStatisticsDTO;
use App\DTOs\Statistic\StatisticEntryDTO;
use App\Models\GameSession;
use App\Models\SessionEventCount;
use App\Models\SessionMapStat;
use App\Models\SessionStatisticEntry;
use Illuminate\Support\Facades\DB;

class StoreSessionStatistics
{
    private const CHUNK_SIZE = 1000;

    public function exec(GameSession $gameSession, SessionStatisticsDTO $statistics): void
    {
        DB::transaction(function () use ($gameSession, $statistics) {
            $this->purge($gameSession);

            $userId = (int) $gameSession->getAttribute('user_id');

            foreach (array_chunk($this->entryRows($statistics, $gameSession->id, $userId), self::CHUNK_SIZE) as $chunk) {
                SessionStatisticEntry::insert($chunk);
            }

            SessionEventCount::insert($this->eventCountRows($statistics, $gameSession->id, $userId));
            SessionMapStat::insert($this->mapRows($statistics, $gameSession->id, $userId));

            $gameSession->update([
                'duration_seconds' => $statistics->durationSeconds,
                'points_count' => $statistics->pointsCount,
            ]);
        });
    }

    public function purge(GameSession $gameSession): void
    {
        SessionStatisticEntry::query()->where('game_session_id', $gameSession->id)->delete();
        SessionEventCount::query()->where('game_session_id', $gameSession->id)->delete();
        SessionMapStat::query()->where('game_session_id', $gameSession->id)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entryRows(SessionStatisticsDTO $statistics, int $gameSessionId, int $userId): array
    {
        return array_map(
            fn (StatisticEntryDTO $entry) => [
                'game_session_id' => $gameSessionId,
                'user_id' => $userId,
                ...$entry->toArray(),
            ],
            $statistics->entries,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventCountRows(SessionStatisticsDTO $statistics, int $gameSessionId, int $userId): array
    {
        $rows = [];

        foreach ($statistics->eventCounts as $eventTypeId => $total) {
            $rows[] = [
                'game_session_id' => $gameSessionId,
                'user_id' => $userId,
                'event_type_id' => $eventTypeId,
                'total' => $total,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapRows(SessionStatisticsDTO $statistics, int $gameSessionId, int $userId): array
    {
        $rows = [];

        foreach ($statistics->maps as $mapId => $map) {
            $rows[] = [
                'game_session_id' => $gameSessionId,
                'user_id' => $userId,
                'map_id' => $mapId,
                ...$map,
            ];
        }

        return $rows;
    }
}
