<?php

namespace App\Support;

use App\Models\User;

/**
 * Where a signed-in user belongs after logging in or registering.
 *
 * Administrators land in the back office they can actually open. Staff without
 * membership are not operators, and customers belong in the storefront, so
 * neither is sent to an admin page that would answer 403.
 */
class HomePath
{
    public static function forUser(?User $user): string
    {
        if ($user === null) {
            return '/';
        }

        if ($user->isPlatformAdministrator()
            || RestaurantContext::forUser($user) !== null) {
            return '/admin';
        }

        return '/orders';
    }
}
