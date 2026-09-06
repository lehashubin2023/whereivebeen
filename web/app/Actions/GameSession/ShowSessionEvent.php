<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\SessionEventDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Cache;

class ShowSessionEvent
{
    private const CACHE_MINUTES = 10;

    public function __construct(
        private readonly DescribeEvent $describeEvent,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function exec(GameSession $gameSession, int $sequence): ?array
    {
        return Cache::remember(
            $this->cacheKey($gameSession, $sequence),
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->build($gameSession, $sequence),
        );
    }

    private function cacheKey(GameSession $gameSession, int $sequence): string
    {
        return sprintf('game-session:%d:event:%d', $gameSession->id, $sequence);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function build(GameSession $gameSession, int $sequence): ?array
    {
        $event = Event::query()
            ->where('events.game_session_id', $gameSession->id)
            ->where('events.sequence', $sequence)
            ->leftJoin('way_points', function (JoinClause $join) {
                $join->on('way_points.game_session_id', '=', 'events.game_session_id')
                    ->on('way_points.sequence', '=', 'events.sequence');
            })
            ->first([
                'events.sequence',
                'events.event_type_id',
                'events.payload',
                'way_points.time',
            ]);

        if ($event === null) {
            return null;
        }

        $type = EventTypeEnum::tryFrom((int) $event->getAttribute('event_type_id'));

        if ($type === null || ! $type->hasMarker()) {
            return null;
        }

        $time = $event->getAttribute('time');

        return SessionEventDTO::fromArray([
            'sequence' => $event->getAttribute('sequence'),
            'type' => $type->slug(),
            'label' => $type->label(),
            'time' => $time === null
                ? null
                : $gameSession->session_start_at->copy()->addMilliseconds((int) $time * 100)->toIso8601String(),
            'details' => $this->describeEvent->exec($type, (array) $event->getAttribute('payload')),
        ])->toArray();
    }
}
