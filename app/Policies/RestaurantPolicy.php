<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    /**
     * Public visitors reach the public endpoints directly; this covers
     * authenticated reads, including inactive restaurants for their members.
     */
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $restaurant->is_active
            || $user->isPlatformAdministrator()
            || $restaurant->hasMember($user);
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->isPlatformAdministrator()
            || $restaurant->hasMember($user, MembershipRole::Admin);
    }

    /**
     * Creating restaurants and toggling activation is platform-level.
     */
    public function create(User $user): bool
    {
        return $user->isPlatformAdministrator();
    }

    public function manageActivation(User $user, Restaurant $restaurant): bool
    {
        return $user->isPlatformAdministrator();
    }

    public function manageMembers(User $user, Restaurant $restaurant): bool
    {
        return $user->isPlatformAdministrator()
            || $restaurant->hasMember($user, MembershipRole::Admin);
    }

    public function manageMenu(User $user, Restaurant $restaurant): bool
    {
        return $user->isPlatformAdministrator()
            || $restaurant->hasMember($user, MembershipRole::Admin);
    }

    public function viewKpis(User $user, Restaurant $restaurant): bool
    {
        return $user->isPlatformAdministrator()
            || $restaurant->hasMember($user, MembershipRole::Admin);
    }
}
