<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantMember>
 */
class RestaurantMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'user_id' => User::factory()->staff(),
            'role' => MembershipRole::Staff,
            'staff_type' => StaffType::Cashier,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => MembershipRole::Admin,
            'staff_type' => null,
        ]);
    }

    public function driver(): static
    {
        return $this->state(fn (array $attributes): array => [
            'staff_type' => StaffType::Driver,
        ]);
    }
}
