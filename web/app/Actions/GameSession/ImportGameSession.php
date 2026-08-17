<?php

namespace App\Actions\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\CreateWay;
use App\Actions\GameSession\DecodeRawInput;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Models\User;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private DecodeRawInput $decoder,
        private CreateWay $createWay
    )
    {}

    public function exec(string $rawInput, User $user): ?int
    {
        $decodedInput = $this->decoder->exec($rawInput);
        $validated = GameSessionJsonValidator::validate($decodedInput);

        $gameSession = DB::transaction(function () use ($validated, $user) {
            $gameSession = $this->createGameSession->exec(
                CreateGameSessionDTO::fromArray($validated),
                $user
            );

            $this->createWay->exec($gameSession, $validated['points']);

            return $gameSession;
        });

        return $gameSession?->id;
    }
}
