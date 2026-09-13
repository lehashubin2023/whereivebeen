<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\WayPoint;

/**
 * Длительность сессии — это время от первой до последней точки за вычетом
 * отлучек: аддон продолжает сессию через выходы из игры и пишет длину каждой
 * отлучки в событие `gap`.
 */
class MeasureSessionTime
{
    /**
     * @param  array<int, int>  $sessionIds
     * @return array<int, int> секунды по id сессии
     */
    public function exec(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $tracked = WayPoint::query()
            ->whereIn('game_session_id', $sessionIds)
            ->selectRaw('game_session_id, max(`time`) as tracked')
            ->groupBy('game_session_id')
            ->pluck('tracked', 'game_session_id')
            ->map(fn ($value) => (int) round((int) $value / 10))
            ->all();

        foreach ($this->offline($sessionIds) as $sessionId => $seconds) {
            $tracked[$sessionId] = max(0, ($tracked[$sessionId] ?? 0) - $seconds);
        }

        return $tracked;
    }

    /**
     * @param  array<int, int>  $sessionIds
     * @return array<int, int>
     */
    private function offline(array $sessionIds): array
    {
        $offline = [];

        Event::query()
            ->whereIn('game_session_id', $sessionIds)
            ->where('event_type_id', EventTypeEnum::GAP->value)
            ->select(['game_session_id', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$offline) {
                $payload = $event->getAttribute('payload');

                if (! is_array($payload) || ! isset($payload['seconds'])) {
                    return;
                }

                $id = (int) $event->getAttribute('game_session_id');
                $offline[$id] = ($offline[$id] ?? 0) + (int) $payload['seconds'];
            });

        return $offline;
    }
}
