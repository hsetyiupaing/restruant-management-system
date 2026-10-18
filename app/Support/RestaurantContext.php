<?php

namespace App\Support;

use App\Enums\MembershipRole;
use App\Models\Restaurant;
use App\Models\User;

/**
 * Resolves which restaurant the signed-in staff member is operating.
 *
 * A restaurant manager usually holds `restaurant_members.role = ADMIN` without a
 * platform role, so the admin screens are gated on membership rather than on
 * `users.role`. Platform administrators may fall back to any active restaurant.
 */
class RestaurantContext
{
    /**
     * First restaurant the user administers, or the storefront restaurant for a
     * platform administrator. Null means the user cannot open the back office.
     */
    public static function forUser(?User $user): ?Restaurant
    {
        if ($user === null) {
            return null;
        }

        $administered = Restaurant::query()
            ->whereHas('members', fn ($query) => $query
                ->where('user_id', $user->id)
                ->where('role', MembershipRole::Admin))
            ->orderBy('name')
            ->first();

        if ($administered !== null) {
            return $administered;
        }

        if (! $user->isPlatformAdministrator()) {
            return null;
        }

        return self::storefrontRestaurant();
    }

    /**
     * The venue the customer storefront serves. A platform administrator has no
     * membership of their own, so the back office must default to this same
     * restaurant — otherwise the admin menu and the customer menu show
     * different venues on the same site.
     */
    public static function storefrontRestaurant(): ?Restaurant
    {
        return Restaurant::query()
            ->where('slug', config('restaurant.storefront_slug'))
            ->where('is_active', true)
            ->first()
            ?? Restaurant::query()->where('is_active', true)->orderBy('name')->first()
            ?? Restaurant::query()->orderBy('name')->first();
    }

    public static function canManage(?User $user, Restaurant $restaurant): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isPlatformAdministrator()
            || $restaurant->hasMember($user, MembershipRole::Admin);
    }
}
