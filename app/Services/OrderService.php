<?php

namespace App\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const DETAIL_RELATIONS = [
        'items', 'deliveryDetail', 'statusHistory', 'restaurant', 'reservation',
    ];

    public function __construct(private readonly OrderNumberGenerator $orderNumbers) {}

    /**
     * Create a pickup or delivery order from validated checkout input.
     *
     * All monetary values are recomputed from the database; nothing the client
     * sent for price, tax, fee, discount or total is trusted.
     *
     * Expected keys: `order_type`, `items` (each with `menu_item_id`, optional
     * `quantity` and `note`), `customer_note`, `scheduled_for`,
     * `reservation_id`, and `delivery` (`recipient_name`, `recipient_phone`,
     * `address_line`, `latitude`, `longitude`) for delivery orders.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $customer, Restaurant $restaurant, array $data, ?string $idempotencyKey = null): Order
    {
        $orderType = OrderType::from($data['order_type']);

        $items = $this->resolveItems($restaurant, $data['items']);

        $subtotal = $items->sum(fn (array $line): float => $line['line_total']);

        if ($orderType === OrderType::Delivery && (float) $restaurant->min_delivery_amount > $subtotal) {
            throw ApiException::invalid(
                'The order does not meet the minimum delivery amount.',
                ['items' => ["Minimum delivery order is {$restaurant->min_delivery_amount}."]],
            );
        }

        $deliveryFee = $orderType === OrderType::Delivery ? (float) $restaurant->delivery_fee : 0.0;
        $tax = round($subtotal * (float) $restaurant->tax_rate, 2);
        $discount = 0.0;
        $total = round($subtotal + $deliveryFee + $tax - $discount, 2);

        return DB::transaction(function () use (
            $customer, $restaurant, $data, $orderType, $items, $subtotal, $deliveryFee, $tax, $discount, $total, $idempotencyKey
        ): Order {
            if ($idempotencyKey !== null) {
                $existing = $this->findIdempotentOrder($customer, $idempotencyKey);

                if ($existing !== null) {
                    return $existing;
                }
            }

            $order = $this->insertWithUniqueNumber([
                'restaurant_id' => $restaurant->id,
                'customer_id' => $customer->id,
                'reservation_id' => $data['reservation_id'] ?? null,
                'order_type' => $orderType,
                'status' => OrderStatus::Placed,
                'payment_status' => PaymentStatus::Unpaid,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            foreach ($items as $line) {
                $order->items()->create([
                    'menu_item_id' => $line['menu_item_id'],
                    'item_name_snapshot' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'note' => $line['note'],
                ]);
            }

            if ($orderType === OrderType::Delivery) {
                $delivery = $data['delivery'] ?? null;

                if ($delivery === null) {
                    throw ApiException::invalid(
                        'Delivery details are required for delivery orders.',
                        ['delivery' => ['Delivery details are required for delivery orders.']],
                    );
                }

                $order->deliveryDetail()->create([
                    'driver_id' => null,
                    'recipient_name' => $delivery['recipient_name'],
                    'recipient_phone' => $delivery['recipient_phone'],
                    'address_line' => $delivery['address_line'],
                    'latitude' => $delivery['latitude'] ?? null,
                    'longitude' => $delivery['longitude'] ?? null,
                    'delivery_status' => DeliveryStatus::Unassigned,
                ]);
            }

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => OrderStatus::Placed->value,
                'changed_by' => $customer->id,
                'changed_at' => now(),
                'reason' => 'Order placed',
            ]);

            if ($idempotencyKey !== null) {
                Cache::put($this->idempotencyStoreKey($customer, $idempotencyKey), $order->id, now()->addDay());
            }

            return $order->load(self::DETAIL_RELATIONS);
        });
    }

    /**
     * Apply a status transition on behalf of an authorized actor.
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor, ?string $reason = null, bool $asAdministrator = false): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $reason, $asAdministrator): Order {
            $order->refresh();

            $allowed = $order->status->staffTransitions($order->order_type);

            if ($asAdministrator) {
                $allowed = [...$allowed, ...$order->status->administratorTransitions()];
            }

            if (! in_array($to, $allowed, true)) {
                throw ApiException::invalid(
                    "Cannot move order from {$order->status->value} to {$to->value}.",
                    ['status' => ['This status transition is not allowed.']],
                );
            }

            $from = $order->status;

            $order->status = $to;

            if ($to === OrderStatus::Completed && $order->order_type === OrderType::Pickup) {
                $order->payment_status = PaymentStatus::Paid;
            }

            $order->save();

            $order->statusHistory()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'changed_by' => $actor?->id,
                'changed_at' => now(),
                'reason' => $reason,
            ]);

            return $order->load(self::DETAIL_RELATIONS);
        });
    }

    /**
     * Reload order state and synchronise order.payment_status from its payment rows.
     */
    public function syncPaymentStatus(Order $order): Order
    {
        $order->refresh();

        $payments = $order->payments()->get();

        $status = match (true) {
            $payments->contains(fn ($payment) => $payment->status === PaymentStatus::Paid) => PaymentStatus::Paid,
            $payments->contains(fn ($payment) => $payment->status === PaymentStatus::Refunded) => PaymentStatus::Refunded,
            $payments->contains(fn ($payment) => $payment->status === PaymentStatus::Failed) => PaymentStatus::Failed,
            default => PaymentStatus::Unpaid,
        };

        if ($order->payment_status !== $status) {
            $order->payment_status = $status;
            $order->save();
        }

        return $order;
    }

    /**
     * Resolve requested lines against the restaurant's own menu. Duplicate
     * lines for the same item are merged; every price comes from the database.
     *
     * @param  array<int|string, mixed>  $requested
     * @return Collection<int, array{menu_item_id: int, name: string, unit_price: float, quantity: int, line_total: float, note: non-empty-string|null}>
     */
    private function resolveItems(Restaurant $restaurant, array $requested): Collection
    {
        $quantities = [];
        $notes = [];

        foreach ($requested as $line) {
            if (! is_array($line) || ! isset($line['menu_item_id'], $line['quantity'])) {
                throw ApiException::invalid(
                    'Each order line needs a menu item and a quantity.',
                    ['items' => ['Each order line needs a menu item and a quantity.']],
                );
            }

            $menuItemId = (int) $line['menu_item_id'];
            $quantity = (int) $line['quantity'];
            $quantities[$menuItemId] = ($quantities[$menuItemId] ?? 0) + $quantity;

            if (isset($line['note']) && is_string($line['note']) && $line['note'] !== '') {
                $notes[$menuItemId] = $line['note'];
            }
        }

        $menuItems = MenuItem::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('id', array_keys($quantities))
            ->get()
            ->keyBy('id');

        if ($menuItems->count() !== count($quantities)) {
            throw ApiException::invalid(
                'One or more menu items do not belong to this restaurant.',
                ['items' => ['An item in the cart is not on this restaurant\'s menu.']],
            );
        }

        $unavailable = $menuItems->reject(fn (MenuItem $item): bool => $item->is_available);

        if ($unavailable->isNotEmpty()) {
            throw ApiException::invalid(
                'One or more items are unavailable.',
                ['items' => $unavailable->pluck('name')->map(fn (string $name): string => "{$name} is sold out.")->all()],
            );
        }

        return $menuItems->map(function (MenuItem $item) use ($quantities, $notes): array {
            $quantity = $quantities[$item->id];
            $unitPrice = (float) $item->price;

            return [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => round($unitPrice * $quantity, 2),
                'note' => $notes[$item->id] ?? null,
            ];
        })->values();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insertWithUniqueNumber(array $attributes): Order
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return Order::query()->create([
                    ...$attributes,
                    'order_number' => $this->orderNumbers->generate(),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 5 || ! str_contains($e->getMessage(), 'order_number')) {
                    throw $e;
                }
            }
        }

        throw ApiException::conflict('Could not allocate a unique order number. Please retry.');
    }

    private function findIdempotentOrder(User $customer, string $key): ?Order
    {
        $storedId = Cache::get($this->idempotencyStoreKey($customer, $key));

        if ($storedId === null) {
            return null;
        }

        return Order::query()
            ->where('customer_id', $customer->id)
            ->find((int) $storedId);
    }

    private function idempotencyStoreKey(User $customer, string $key): string
    {
        return 'order:'.$customer->id.':'.hash('sha256', $key);
    }
}
