<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($actor->isMainAdmin() || $actor->is($target)) {
            return true;
        }

        return ! $target->isAdmin();
    }

    public function delete(User $actor, User $target): bool
    {
        if (! $actor->isAdmin() || $actor->is($target) || $target->isMainAdmin()) {
            return false;
        }

        return $target->isAdmin() ? $actor->isMainAdmin() : true;
    }

    public function manageAdmins(User $actor): bool
    {
        return $actor->isMainAdmin();
    }
}
