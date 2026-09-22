<?php

namespace App\Actions\Journey;

use App\Models\GameSession;
use App\Models\User;

class BuildUserJourney
{
    public function __construct(
        private readonly CalculateUserActivity $calculateActivity,
        private readonly CalculateZoneStatistics $calculateZones,
        private readonly TrackPlayerLevels $trackLevels,
        private readonly FindDeadliestZone $findDeadliestZone,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function exec(User $user): array
    {
        $sessions = GameSession::query()
            ->where('user_id', $user->id)
            ->get(['id', 'session_start_at']);

        if ($sessions->isEmpty()) {
            return ['activity' => [], 'zones' => [], 'levels' => [], 'deadliest' => null];
        }

        $ids = $sessions->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [
            'activity' => $this->calculateActivity->exec($sessions, $ids),
            'zones' => $this->calculateZones->exec($ids),
            'levels' => $this->trackLevels->exec($sessions, $ids),
            'deadliest' => $this->findDeadliestZone->exec($ids),
        ];
    }
}
