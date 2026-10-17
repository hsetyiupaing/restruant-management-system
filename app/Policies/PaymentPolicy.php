<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->order->customer_id === $user->id
            || $user->isPlatformAdministrator()
            || $payment->order->restaurant->hasMember($user);
    }

    /**
     * Only restaurant administrators or platform administrators may reverse
     * a captured payment.
     */
    public function refund(User $user, Payment $payment): bool
    {
        return $payment->status === PaymentStatus::Paid
            && ($user->isPlatformAdministrator()
                || $payment->order->restaurant->hasMember($user));
    }
}
