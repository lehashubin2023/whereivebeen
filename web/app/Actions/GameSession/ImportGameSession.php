<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Models\User;
use App\Support\GameSession\ImportProgress\ImportGameSessionProgressContract;
use App\Support\GameSession\ImportProgress\NullImportGameSessionProgress;
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

        return DB::transaction(function () use ($validated, $user, $progress) {
            $gameSession = $this->createGameSession->exec(
                CreateGameSessionDTO::fromArray($validated),
                $user
            );

            $this->createWay->exec($gameSession, $validated['points'], $progress);

            return $gameSession->id;
        });
    }
}
