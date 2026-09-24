<?php

namespace App\Actions\GameSession\Import;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\CreateWay;
use App\Actions\Map\RegisterMissingMaps;
use App\Actions\Statistic\CollectSessionStatistics;
use App\Actions\Statistic\StoreSessionStatistics;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Enums\GameSession\ImportOutcomeEnum;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgressContract;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
use App\Support\GameSession\MapId;
use App\Support\Statistic\PointSource\ImportedPointSource;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private CreateGameSession $createGameSession,
        private DecodeRawInput $decoder,
        private CreateWay $createWay,
        private RegisterMissingMaps $registerMissingMaps,
        private CollectSessionStatistics $collectStatistics,
        private StoreSessionStatistics $storeStatistics
    ) {}

    public function exec(
        string $rawInput,
        User $user,
        ImportGameSessionProgressContract $progress = new NullImportGameSessionProgress
    ): int {
        $decodedInput = $this->decoder->exec($rawInput);
        $validated = GameSessionJsonValidator::validate($decodedInput);

        /** @var array<int, array<string, mixed>> $points */
        $points = (array) $validated['points'];

        $progress->process(count($points));

        $newMapIds = $this->registerMissingMaps->exec(MapId::distinctFromPoints($points));

        $gameSessionId = DB::transaction(function () use ($validated, $points, $user, $progress) {
            $gameSession = $this->createGameSession->exec(
                CreateGameSessionDTO::fromArray($validated),
                $user
            );

            $isNew = $gameSession->wasRecentlyCreated;

            $progress->outcome($isNew
                ? ImportOutcomeEnum::CREATED
                : ImportOutcomeEnum::REPLACED);

            $this->createWay->exec($gameSession, $points, $progress);

            $this->storeStatistics->exec(
                $gameSession,
                $this->collectStatistics->exec(new ImportedPointSource($points)),
                $isNew === false
            );

            return $gameSession->id;
        }, self::TRANSACTION_ATTEMPTS);

        $this->storeStatistics->markStale((int) $user->id);

        if ($newMapIds !== []) {
            $progress->warn(['new_maps' => $newMapIds]);
        }

        return $gameSessionId;
    }
}
