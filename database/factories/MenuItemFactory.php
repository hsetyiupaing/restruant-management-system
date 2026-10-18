<?php

namespace Database\Factories;

use App\Enums\MenuCourse;
use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var string $name */
        $name = fake()->unique()->words(3, true);

        return [
            'restaurant_id' => fn (array $attributes): int => (int) Category::query()
                ->whereKey($attributes['category_id'])
                ->value('restaurant_id'),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'course' => fake()->randomElement(MenuCourse::cases()),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 60, 420),
            'image_url' => null,
            'is_available' => true,
            'is_featured' => false,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_available' => false,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_featured' => true,
        ]);
    }

    public function course(MenuCourse $course): static
    {
        return $this->state(fn (array $attributes): array => [
            'course' => $course,
        ]);
    }
}
