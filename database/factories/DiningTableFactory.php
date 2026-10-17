<?php

namespace Database\Factories;

use App\Enums\TableShape;
use App\Models\DiningTable;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'label' => 'T'.fake()->unique()->numerify('##'),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
            'min_capacity' => 1,
            'pos_x' => fake()->numberBetween(0, 800),
            'pos_y' => fake()->numberBetween(0, 600),
            'shape' => fake()->randomElement(TableShape::cases()),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
