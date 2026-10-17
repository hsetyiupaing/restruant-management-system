<?php

namespace App\Policies;

use App\Models\DeliveryDetail;
use App\Models\User;

class DeliveryDetailPolicy
{
    public function view(User $user, DeliveryDetail $delivery): bool
    {
        return $delivery->order->customer_id === $user->id
            || $user->isPlatformAdministrator()
            || $delivery->order->restaurant->hasMember($user);
    }

    public function update(User $user, DeliveryDetail $delivery): bool
    {
        return $user->isPlatformAdministrator()
            || $delivery->order->restaurant->hasMember($user);
    }
}
