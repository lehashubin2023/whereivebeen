<?php

namespace App\Jobs;

use App\Actions\GameSession\DecodeGameSession;
use App\Actions\GameSession\ParseGameSession;
use App\Exceptions\InvalidGameSessionInputException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Queue;

#[Queue('import')]
class ParseGameSessionJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $gameSessionInput,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        DecodeGameSession $decoder, 
        ParseGameSession $parser
    ): void
    {
        try {
            $decodedInput = $decoder->exec($this->gameSessionInput);
            $parser->exec($decodedInput);
        } catch (InvalidGameSessionInputException $e) {
            // report($e);
            // $this->delete(); 
        } catch (\Throwable $e) {
            report($e);
        }
    }
}