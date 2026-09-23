<?php

namespace App\Actions\Statistic;

use App\Models\GameSession;
use App\Support\Statistic\PointSource\StoredPointSource;

class CollectStoredSessionStatistics
{
    public function __construct(
        private readonly CollectSessionStatistics $collect,
        private readonly StoreSessionStatistics $store,
    ) {}

    public function exec(GameSession $gameSession): void
    {
        $this->store->exec($gameSession, $this->collect->exec(new StoredPointSource($gameSession->id)));
    }
}
