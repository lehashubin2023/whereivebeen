<?php

namespace App\Actions\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\createWay;
use App\Actions\GameSession\CreateWay;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\ImportLog;
use App\Models\User;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private DecodeGameSession $decoder,
        private CreateWay $createWay
    )
    {}

    public function exec(ImportLog $importLog, string $rawInput, User $user): void
    {
        $decodedInput = $this->decoder->exec($rawInput);

        $validated = GameSessionJsonValidator::validate($decodedInput);

        DB::transaction(function () use ($validated, $user, $importLog) {
            $gameSession = $this->createGameSession->exec(
                CreateGameSessionDTO::fromArray($validated), 
                $user
            );

            $this->createWay->exec($gameSession, $validated['points']);

            $importLog->update([
                'status' => ImportStatusEnum::COMPLETED,
                'execution_time' => strtotime($importLog->created_at) - time(),
            ]);
        });
    }
}
