<?php

namespace App\Actions\User;

use App\DTOs\User\UpdateUserDTO;
use App\Models\User;

class UpdateUser
{
    public function exec(User $user, UpdateUserDTO $dto): User
    {
        if ($user->email !== $dto->email) {
            $user->email = $dto->email;
            $user->email_verified_at = null;
        }

        if ($dto->password !== null) {
            $user->password = $dto->password;
        }

        $user->is_admin = $dto->is_admin;
        $user->save();

        return $user;
    }
}
