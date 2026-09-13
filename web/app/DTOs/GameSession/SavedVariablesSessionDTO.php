<?php

namespace App\DTOs\GameSession;

final readonly class SavedVariablesSessionDTO
{
    /**
     * @param  array<string, mixed>  $header
     */
    public function __construct(
        public int $sessionId,
        public array $header,
        public string $spoolPath,
        public int $pointsCount,
    ) {}

    public function character(): ?string
    {
        $character = $this->header['char'] ?? null;

        return is_string($character) && $character !== '' ? $character : null;
    }

    public function realm(): ?string
    {
        $realm = $this->header['realm'] ?? null;

        return is_string($realm) && $realm !== '' ? $realm : null;
    }

    public function startedAt(): ?int
    {
        $started = $this->header['started'] ?? null;

        return is_numeric($started) && (int) $started > 0 ? (int) $started : null;
    }
}
