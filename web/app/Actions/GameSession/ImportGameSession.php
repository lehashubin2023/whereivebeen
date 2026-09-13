<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Enums\GameSession\ImportOutcomeEnum;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgressContract;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
use App\Support\GameSession\MapIdFilter;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private DecodeRawInput $decoder,
        private CreateWay $createWay
    ) {}

    public function exec(
        string $rawInput,
        User $user,
        ImportGameSessionProgressContract $progress = new NullImportGameSessionProgress
    ): int {
        $decodedInput = $this->decoder->exec($rawInput);
        $validated = GameSessionJsonValidator::validate($decodedInput);

        $progress->process(count((array) $validated['points']));

        $maps = MapIdFilter::fromDatabase();

        $gameSessionId = DB::transaction(function () use ($validated, $user, $progress, $maps) {
            $gameSession = $this->createGameSession->exec(
                CreateGameSessionDTO::fromArray($validated),
                $user
            );

            $progress->outcome($gameSession->wasRecentlyCreated
                ? ImportOutcomeEnum::CREATED
                : ImportOutcomeEnum::REPLACED);

            $this->createWay->exec($gameSession, $validated['points'], $progress, $maps);

            return $gameSession->id;
        });

        if ($maps->hasUnknown()) {
            $progress->warn(['unknown_maps' => $maps->unknown()]);
        }

        return $gameSessionId;
    }
}
