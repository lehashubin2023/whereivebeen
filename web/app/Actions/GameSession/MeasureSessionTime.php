<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class MeasureSessionTime
{
    private const STEP_LIMIT = 600;

    private const GAP_OF_UNKNOWN_LENGTH = -1;

    /**
     * @param  array<int, int>  $sessionIds
     * @return array<int, int>
     */
    public function exec(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }

        $offline = $this->offline($sessionIds);
        $seconds = [];
        $previousSession = null;
        $previousTime = 0;

        DB::table('way_points')
            ->whereIn('game_session_id', $sessionIds)
            ->select(['game_session_id', 'sequence', 'time'])
            ->orderBy('game_session_id')
            ->orderBy('sequence')
            ->cursor()
            ->each(function (object $point) use (&$seconds, &$previousSession, &$previousTime, $offline) {
                $sessionId = (int) $point->game_session_id;
                $time = (int) $point->time;

                $seconds[$sessionId] ??= 0;

                if ($previousSession === $sessionId) {
                    $gap = $offline[$sessionId][(int) $point->sequence] ?? 0;

                    $step = $gap === self::GAP_OF_UNKNOWN_LENGTH
                        ? 0
                        : (int) round(($time - $previousTime) / 10) - $gap;

                    $seconds[$sessionId] += max(0, min($step, self::STEP_LIMIT));
                }

                $previousSession = $sessionId;
                $previousTime = $time;
            });

        return $seconds;
    }

    /**
     * @param  array<int, int>  $sessionIds
     * @return array<int, array<int, int>>
     */
    private function offline(array $sessionIds): array
    {
        $offline = [];

        Event::query()
            ->whereIn('game_session_id', $sessionIds)
            ->where('event_type_id', EventTypeEnum::GAP->value)
            ->select(['game_session_id', 'sequence', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$offline) {
                $payload = $event->getAttribute('payload');

                $offline[(int) $event->getAttribute('game_session_id')][(int) $event->getAttribute('sequence')]
                    = is_array($payload) && isset($payload['seconds'])
                        ? (int) $payload['seconds']
                        : self::GAP_OF_UNKNOWN_LENGTH;
            });

        return $offline;
    }
}
