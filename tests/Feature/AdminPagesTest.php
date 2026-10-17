<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

test('guests are redirected from the back office to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with([
    'admin.dashboard',
    'admin.orders.index',
    'admin.reservations.index',
    'admin.menu.index',
    'admin.customers.index',
    'admin.settings.index',
]);

test('a user without a restaurant membership is forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('a restaurant administrator sees the dashboard for the restaurant they manage', function () {
    $restaurant = Restaurant::factory()->create(['name' => 'Ember & Oak']);
    $admin = User::factory()->create();

    RestaurantMember::factory()->admin()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('restaurant.id', $restaurant->id)
            ->where('canManageMenu', true)
        );
});

test('staff-only members cannot reach the back office', function () {
    $restaurant = Restaurant::factory()->create();
    $staff = User::factory()->staff()->create();

    RestaurantMember::factory()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $staff->id,
    ]);

    $this->actingAs($staff)
        ->get(route('admin.orders.index'))
        ->assertForbidden();
});

test('the order detail page passes the order id and keeps the route restaurant scoped', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();

    RestaurantMember::factory()->admin()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $admin->id,
    ]);

    $order = Order::factory()->create(['restaurant_id' => $restaurant->id]);

    $this->actingAs($admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Orders/Show')
            ->where('orderId', $order->id)
        );
});

test('a platform administrator falls back to the first active restaurant', function () {
    $restaurant = Restaurant::factory()->create([
        'name' => 'Alpha Kitchen',
        'is_active' => true,
    ]);

    Restaurant::factory()->inactive()->create(['name' => 'Closed Kitchen']);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('restaurant.id', $restaurant->id)
        );
});
