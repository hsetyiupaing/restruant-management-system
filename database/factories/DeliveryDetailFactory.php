<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\DeliveryDetail;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryDetail>
 */
class DeliveryDetailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->delivery(),
            'driver_id' => null,
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->phoneNumber(),
            'address_line' => fake()->streetAddress(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'delivery_status' => DeliveryStatus::Unassigned,
            'assigned_at' => null,
            'picked_up_at' => null,
            'delivered_at' => null,
            'failure_reason' => null,
        ];
    }
}
