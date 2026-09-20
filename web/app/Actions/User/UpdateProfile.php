<?php

namespace App\Actions\User;

use App\DTOs\User\UpdateProfileDTO;
use App\Models\User;

class UpdateProfile
{
    public function exec(User $user, UpdateProfileDTO $dto): User
    {
        if ($user->email !== $dto->email) {
            $user->email = $dto->email;
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }
}
