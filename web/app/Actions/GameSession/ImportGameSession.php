<?php

namespace App\Actions\GameSession;

use App\Actions\GameSession\CreateGameSession;
use App\Actions\GameSession\createWay;
use App\Actions\GameSession\CreateWay;
use App\DTOs\GameSession\CreateGameSessionDTO;
use App\Enums\GameSession\ImportStatusEnum;
use App\Models\User;
use App\Validators\GameSessionJsonValidator;
use Illuminate\Support\Facades\DB;

class ImportGameSession
{
    public function __construct(
        private CreateGameSession $createGameSession,
        private CreateWay $createWay
    )
    {}

    public function exec(array $input, User $user): void
    {
        $validated = GameSessionJsonValidator::validate($input);
        $startedAt = time();

        $gameSession = $this->createGameSession->exec(
            CreateGameSessionDTO::fromArray($validated), 
            $user
        );

        try {
            DB::transaction(function () use ($validated, $gameSession, $startedAt) {
                $this->createWay->exec($gameSession, $validated['points']);
                $gameSession->update([
                    'import_status' => ImportStatusEnum::COMPLETED, 
                    'execution_time' => time() - $startedAt
                ]);
            });
        } catch (\Throwable $e) {
            $gameSession->update([
                'import_status' => ImportStatusEnum::FAILED,
                'import_error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
