<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;

test('the home page renders the configured restaurant with featured dishes', function () {
    $restaurant = Restaurant::factory()->openDaily()->create([
        'slug' => 'ember-and-oak',
        'name' => 'Ember & Oak',
    ]);

    $featured = MenuItem::factory()->featured()->create([
        'restaurant_id' => $restaurant->id,
        'category_id' => Category::factory()->create(['restaurant_id' => $restaurant->id])->id,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'category_id' => Category::factory()->create(['restaurant_id' => $restaurant->id])->id,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Home')
            ->where('restaurant.slug', 'ember-and-oak')
            ->where('totalDishes', 2)
            ->has('featuredDishes', 1)
            ->where('featuredDishes.0.name', $featured->name)
            ->has('openingHours')
        );
});

test('the home page renders without a restaurant when none is active', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Home')
            ->where('restaurant', null)
            ->where('featuredDishes', [])
            ->where('totalDishes', 0)
        );
});

test('the menu page lists categories for the configured restaurant', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'ember-and-oak']);

    $category = Category::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Small plates',
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'category_id' => $category->id,
    ]);

    MenuItem::factory()->unavailable()->create([
        'restaurant_id' => $restaurant->id,
        'category_id' => $category->id,
    ]);

    $this->get(route('menu'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Menu')
            ->where('restaurant.slug', 'ember-and-oak')
            ->has('categories', 1)
            ->where('categories.0.name', 'Small plates')
            // Only available items count toward the chip badge.
            ->where('categories.0.menu_items_count', 1)
        );
});

test('guests cannot reach customer account pages', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with(['orders.index', 'reserve', 'checkout']);

test('a signed-in customer can open their order detail page', function () {
    $customer = User::factory()->create();

    $order = Order::factory()->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customer/Orders/Show')
            ->where('orderId', $order->id)
        );
});

test('a customer cannot open another customer order', function () {
    $order = Order::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('orders.show', $order))
        ->assertNotFound();
});
