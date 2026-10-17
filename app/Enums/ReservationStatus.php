<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'PENDING';
    case Confirmed = 'CONFIRMED';
    case Seated = 'SEATED';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
    case Expired = 'EXPIRED';

    /**
     * Allowed transitions performed by restaurant staff.
     *
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled, self::Expired],
            self::Confirmed => [self::Seated, self::Cancelled, self::NoShow],
            self::Seated => [self::Completed, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow, self::Expired => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    /**
     * Statuses that block a table slot from being booked again.
     *
     * @return array<int, self>
     */
    public static function capacityHolding(): array
    {
        return [self::Pending, self::Confirmed, self::Seated];
    }
}
