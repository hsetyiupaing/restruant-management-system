<?php

use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users are sent from the dashboard to the back office', function () {
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();

    RestaurantMember::factory()->admin()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});
