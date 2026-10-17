<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(fake()->numberBetween(2, 10))->setTime(18, 0);

        return [
            'restaurant_id' => Restaurant::factory(),
            'customer_id' => User::factory(),
            'table_id' => DiningTable::factory(),
            'party_size' => 2,
            'start_time' => $start,
            'end_time' => $start->copy()->addMinutes(90),
            'status' => ReservationStatus::Confirmed,
            'special_request' => null,
            'hold_expires_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReservationStatus::Pending,
            'hold_expires_at' => now()->addMinutes(15),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ReservationStatus::Cancelled,
        ]);
    }
}
