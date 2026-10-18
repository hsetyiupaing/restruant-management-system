<?php

namespace Database\Factories;

use App\Enums\FoodRestriction;
use App\Models\CustomerFoodRestriction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerFoodRestriction>
 */
class CustomerFoodRestrictionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $restriction = fake()->randomElement([
            FoodRestriction::Peanuts,
            FoodRestriction::Shellfish,
            FoodRestriction::Milk,
            FoodRestriction::GlutenFreeRequirement,
            FoodRestriction::Vegetarian,
        ]);

        return [
            'user_id' => User::factory(),
            'restriction' => $restriction,
            'kind' => $restriction->kind(),
            'custom_label' => null,
        ];
    }

    public function allergy(FoodRestriction $restriction = FoodRestriction::Peanuts): static
    {
        return $this->state(fn (): array => [
            'restriction' => $restriction,
            'kind' => $restriction->kind(),
            'custom_label' => null,
        ]);
    }

    public function dietaryRestriction(FoodRestriction $restriction = FoodRestriction::CeliacDisease): static
    {
        return $this->state(fn (): array => [
            'restriction' => $restriction,
            'kind' => $restriction->kind(),
            'custom_label' => null,
        ]);
    }
}
