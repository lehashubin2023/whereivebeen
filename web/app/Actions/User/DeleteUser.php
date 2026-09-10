<?php

namespace App\Actions\User;

use App\Models\User;

class DeleteUser
{
    public function exec(User $user): void
    {
        $user->delete();
    }
}
