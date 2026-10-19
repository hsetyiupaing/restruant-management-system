<?php

namespace App\Policies;

use App\Models\User;

/**
 * Account and role administration is platform-level. Restaurant managers
 * manage their own staff through restaurant_members, not through user roles.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || $user->id === $target->id;
    }

    /**
     * Only a Super Admin may change another user's role. Admins and Staff can
     * never promote themselves or anyone else.
     */
    public function changeRole(User $user, User $target): bool
    {
        return $user->isSuperAdmin() && ! $target->isSuperAdmin();
    }
}
