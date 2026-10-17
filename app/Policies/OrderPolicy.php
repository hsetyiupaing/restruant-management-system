<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->customer_id === $user->id
            || $user->isPlatformAdministrator()
            || $order->restaurant->hasMember($user);
    }

    /**
     * Customers may cancel only while the order is still PLACED.
     */
    public function cancel(User $user, Order $order): bool
    {
        if ($order->status->isTerminal()) {
            return false;
        }

        if ($order->customer_id === $user->id) {
            return $order->status === OrderStatus::Placed;
        }

        if ($user->isPlatformAdministrator() || $order->restaurant->hasMember($user, MembershipRole::Admin)) {
            return $order->status !== OrderStatus::Completed;
        }

        return false;
    }

    public function refund(User $user, Order $order): bool
    {
        return $order->status === OrderStatus::Completed
            && ($user->isPlatformAdministrator()
                || $order->restaurant->hasMember($user, MembershipRole::Admin));
    }

    public function viewPayments(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }

    public function recordPayment(User $user, Order $order): bool
    {
        return $user->isPlatformAdministrator()
            || $order->restaurant->hasMember($user);
    }
}
