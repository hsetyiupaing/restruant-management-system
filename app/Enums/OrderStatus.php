<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Placed = 'PLACED';
    case Confirmed = 'CONFIRMED';
    case Preparing = 'PREPARING';
    case Ready = 'READY';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Rejected = 'REJECTED';
    case DeliveryFailed = 'DELIVERY_FAILED';
    case Refunded = 'REFUNDED';

    /**
     * Transitions an operational staff member may perform.
     *
     * @return array<int, self>
     */
    public function staffTransitions(OrderType $type): array
    {
        return match ($this) {
            self::Placed => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed => [self::Preparing],
            self::Preparing => [self::Ready],
            self::Ready => $type === OrderType::Delivery
                ? [self::OutForDelivery]
                : [self::Completed],
            self::OutForDelivery => [self::Completed, self::DeliveryFailed],
            self::DeliveryFailed => [self::OutForDelivery],
            self::Completed, self::Cancelled, self::Rejected, self::Refunded => [],
        };
    }

    /**
     * Additional transitions reserved for restaurant administrators.
     *
     * @return array<int, self>
     */
    public function administratorTransitions(): array
    {
        return match ($this) {
            self::Confirmed, self::Preparing, self::Ready,
            self::OutForDelivery, self::DeliveryFailed => [self::Cancelled],
            self::Completed => [self::Refunded],
            default => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Cancelled, self::Rejected, self::Refunded], true);
    }

    /**
     * Statuses that count toward revenue when the order was paid.
     *
     * @return array<int, self>
     */
    public static function revenueExcluded(): array
    {
        return [self::Cancelled, self::Rejected, self::Refunded];
    }
}
