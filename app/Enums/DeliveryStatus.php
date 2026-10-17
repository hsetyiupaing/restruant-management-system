<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Unassigned = 'UNASSIGNED';
    case Assigned = 'ASSIGNED';
    case PickedUp = 'PICKED_UP';
    case Delivered = 'DELIVERED';
    case Failed = 'FAILED';

    /**
     * Allowed forward transitions for a delivery record.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unassigned => [self::Assigned],
            self::Assigned => [self::PickedUp, self::Failed],
            self::PickedUp => [self::Delivered, self::Failed],
            self::Delivered, self::Failed => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }
}
