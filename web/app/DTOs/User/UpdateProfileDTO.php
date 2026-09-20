<?php

namespace App\DTOs\User;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class UpdateProfileDTO implements DTOContract
{
    public function __construct(
        public readonly string $email,
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
            email: (string) ($data['email'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
