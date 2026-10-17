<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10, 100);
        $deliveryFee = 0.0;
        $tax = round($subtotal * 0.10, 2);

        return [
            'restaurant_id' => Restaurant::factory(),
            'customer_id' => User::factory(),
            'reservation_id' => null,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
            'order_type' => OrderType::Pickup,
            'status' => OrderStatus::Placed,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'discount' => 0,
            'total' => round($subtotal + $deliveryFee + $tax, 2),
            'scheduled_for' => null,
            'customer_note' => null,
        ];
    }

    public function delivery(): static
    {
        return $this->state(fn (array $attributes): array => [
            'order_type' => OrderType::Delivery,
            'delivery_fee' => 3.50,
            'total' => round((float) $attributes['subtotal'] + 3.50 + (float) $attributes['tax'], 2),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'payment_status' => PaymentStatus::Paid,
        ]);
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }
}
