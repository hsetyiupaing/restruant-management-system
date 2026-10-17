<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->streetAddress(),
            'opening_hours' => null,
            'slot_minutes' => 90,
            'auto_confirm_reservations' => false,
            'delivery_fee' => 3.50,
            'min_delivery_amount' => 15.00,
            'tax_rate' => 0.1000,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function autoConfirm(): static
    {
        return $this->state(fn (array $attributes): array => [
            'auto_confirm_reservations' => true,
        ]);
    }

    /**
     * Open every day 09:00–22:00 so opening-hours validation always passes.
     */
    public function openDaily(): static
    {
        return $this->state(fn (array $attributes): array => [
            'opening_hours' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
                ->mapWithKeys(fn (string $day): array => [$day => ['open' => '09:00', 'close' => '22:00']])
                ->all(),
        ]);
    }
}
