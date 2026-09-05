<?php

namespace App\DTOs\GameSession;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class SessionEventDTO implements DTOContract
{
    /**
     * @param  array<int, array{label: string, value: string}>  $details
     */
    public function __construct(
        private readonly int $sequence,
        private readonly string $type,
        private readonly string $label,
        private readonly ?string $time,
        private readonly array $details,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return self::fromArray($request->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sequence: (int) ($data['sequence'] ?? 0),
            type: (string) ($data['type'] ?? ''),
            label: (string) ($data['label'] ?? ''),
            time: $data['time'] ?? null,
            details: $data['details'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'type' => $this->type,
            'label' => $this->label,
            'time' => $this->time,
            'details' => $this->details,
        ];
    }
}
