<?php

namespace App\Actions\GameSession;

use App\Actions\Map\RegisterMissingMaps;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Enums\GameSession\ImportOutcomeEnum;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgressContract;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
use App\Support\GameSession\MapId;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private DecodeRawInput $decoder,
        private CreateWay $createWay,
        private RegisterMissingMaps $registerMissingMaps
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

            $progress->outcome($gameSession->wasRecentlyCreated
                ? ImportOutcomeEnum::CREATED
                : ImportOutcomeEnum::REPLACED);

            $this->createWay->exec($gameSession, $points, $progress);

            return $gameSession->id;
        });

        if ($newMapIds !== []) {
            $progress->warn(['new_maps' => $newMapIds]);
        }

        return $gameSessionId;
    }
}
