<?php

namespace App\Jobs;

use App\Actions\GameSession\DecodeGameSession;
use App\Actions\GameSession\ImportGameSession;
use App\Exceptions\InvalidGameSessionInputException;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;
#[Queue('import')]
class ImportGameSessionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $gameSessionInput,
        public User $user
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        DecodeGameSession $decoder,
        ImportGameSession $importer
    ): void
    {
        try {
            $decodedInput = $decoder->exec($this->gameSessionInput);
            $importer->exec($decodedInput, $this->user);
        } catch (InvalidGameSessionInputException $e) {
            // report($e);
            // $this->delete(); 
        } catch (\Throwable $e) {
            report($e);
        }
    }
}