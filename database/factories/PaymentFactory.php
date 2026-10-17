<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 10, 100),
            'status' => PaymentStatus::Paid,
            'provider_ref' => null,
            'paid_at' => now(),
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Unpaid,
            'paid_at' => null,
        ]);
    }
}
