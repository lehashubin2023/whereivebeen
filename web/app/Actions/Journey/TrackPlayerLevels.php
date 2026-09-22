<?php

namespace App\Actions\Journey;

use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Collection;

class TrackPlayerLevels
{
    /**
     * @param  Collection<int, GameSession>  $sessions
     * @param  array<int, int>  $ids
     * @return array<int, array{level: int, date: string}>
     */
    public function exec($sessions, array $ids): array
    {
        $startedAt = $sessions->pluck('session_start_at', 'id');
        $levels = [];

        Event::query()
            ->whereIn('game_session_id', $ids)
            ->where('event_type_id', EventTypeEnum::LEVELUP->value)
            ->select(['game_session_id', 'payload'])
            ->cursor()
            ->each(function (Event $event) use (&$levels, $startedAt) {
                $payload = $event->getAttribute('payload');

                if (! is_array($payload) || ! isset($payload['level'])) {
                    return;
                }

                $start = $startedAt[$event->getAttribute('game_session_id')] ?? null;

                if ($start === null) {
                    return;
                }

                $levels[(int) $payload['level']] = $start->toDateString();
            });

        ksort($levels);

        $result = [];

        foreach ($levels as $level => $date) {
            $result[] = ['level' => $level, 'date' => $date];
        }

        return $result;
    }
}
