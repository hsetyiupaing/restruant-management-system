<?php

namespace Database\Factories;

use App\Enums\DietaryRequirement;
use App\Models\DietaryTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DietaryTag>
 */
class DietaryTagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requirement = fake()->randomElement(DietaryRequirement::cases());

        return [
            'name' => $requirement->label(),
            'slug' => $requirement->value,
            'description' => $requirement->description(),
        ];
    }

    public function requirement(DietaryRequirement $requirement): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $requirement->label(),
            'slug' => $requirement->value,
            'description' => $requirement->description(),
        ]);
    }
}
