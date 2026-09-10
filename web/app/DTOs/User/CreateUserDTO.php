<?php

namespace App\DTOs\User;

use App\DTOs\DTOContract;
use Illuminate\Http\Request;

class CreateUserDTO implements DTOContract
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $is_admin,
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
            password: (string) ($data['password'] ?? ''),
            is_admin: (bool) ($data['is_admin'] ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'is_admin' => $this->is_admin,
        ];
    }
}
