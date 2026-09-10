<?php

namespace App\Actions\User;

use App\DTOs\User\CreateUserDTO;
use App\Models\User;

class CreateUser
{
    public function exec(CreateUserDTO $dto): User
    {
        $user = new User([
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        $user->is_admin = $dto->is_admin;
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }
}
