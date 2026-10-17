<?php

namespace App\Policies;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->customer_id === $user->id
            || $user->isPlatformAdministrator()
            || $reservation->restaurant->hasMember($user);
    }

    /**
     * Customers may cancel up to two hours before the start time; staff and
     * administrators may cancel any reservation that is not already terminal.
     */
    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($reservation->status->transitions() === []) {
            return false;
        }

        if ($reservation->customer_id === $user->id) {
            return in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
                && $reservation->start_time->greaterThan(now()->addHours(2));
        }

        return $user->isPlatformAdministrator()
            || $reservation->restaurant->hasMember($user);
    }

    public function updateStatus(User $user, Reservation $reservation): bool
    {
        return $user->isPlatformAdministrator()
            || $reservation->restaurant->hasMember($user);
    }
}
