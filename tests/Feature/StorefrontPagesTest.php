<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\RestaurantContext;

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

test('a dish detail page renders the requested slug for the Vue router', function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slug' => 'mori-thai-kitchen']);
    $category = Category::factory()->for($restaurant)->create();

    MenuItem::factory()->for($restaurant)->for($category)->create([
        'name' => 'Pad Thai',
        'slug' => 'pad-thai',
    ]);

    $this->get(route('menu.show', 'pad-thai'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Dish')
            ->where('dish', 'pad-thai')
            ->where('restaurant.slug', 'mori-thai-kitchen')
        );
});

test('a dish detail page still renders when the slug does not exist', function () {
    $this->get(route('menu.show', 'missing-dish'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Dish')
            ->where('dish', 'missing-dish')
        );
});

test('a platform administrator sees the storefront restaurant in the back office', function () {
    $storefront = Restaurant::factory()->openDaily()->create([
        'slug' => config('restaurant.storefront_slug'),
        'name' => 'Mori Kitchen',
    ]);
    // Alphabetically first, which is what an arbitrary fallback would pick.
    Restaurant::factory()->create(['name' => 'Aardvark Diner']);

    $superAdmin = User::factory()->superAdmin()->create();

    expect(RestaurantContext::forUser($superAdmin)->id)
        ->toBe($storefront->id);

    $this->actingAs($superAdmin)
        ->get('/admin/menu')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Menu/Index')
            ->where('restaurant.id', $storefront->id)
        );

    // The customer side resolves the same venue.
    $this->get(route('menu'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('restaurant.id', $storefront->id));
});
