<?php

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;

test('the public restaurant list hides inactive restaurants', function () {
    $open = Restaurant::factory()->create(['name' => 'Open Kitchen']);
    Restaurant::factory()->inactive()->create(['name' => 'Closed Kitchen']);

    $this->getJson('/api/v1/restaurants')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id);
});

test('a restaurant is browsable by slug', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'spice-route']);

    $this->getJson('/api/v1/restaurants/spice-route')
        ->assertOk()
        ->assertJsonPath('data.slug', $restaurant->slug)
        ->assertJsonPath('data.settings.slot_minutes', 90);
});

test('an inactive restaurant cannot be viewed publicly', function () {
    Restaurant::factory()->inactive()->create(['slug' => 'closed-spot']);

    $this->getJson('/api/v1/restaurants/closed-spot')->assertNotFound();
});

test('the public menu only exposes available items', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'noodle-house']);
    $category = Category::factory()->for($restaurant)->create(['name' => 'Mains']);
    MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Ramen']);
    MenuItem::factory()->for($restaurant)->for($category)->unavailable()->create(['name' => 'Sold Out Soup']);

    $this->getJson('/api/v1/restaurants/noodle-house/menu-items')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ramen');
});

test('menu items can be filtered by category, featured flag and search', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'filter-test']);
    $mains = Category::factory()->for($restaurant)->create(['name' => 'Mains']);
    $drinks = Category::factory()->for($restaurant)->create(['name' => 'Drinks']);
    MenuItem::factory()->for($restaurant)->for($mains)->featured()->create(['name' => 'Nebula Burger']);
    MenuItem::factory()->for($restaurant)->for($drinks)->create(['name' => 'Iced Tea']);
    MenuItem::factory()->for($restaurant)->for($mains)->create(['name' => 'Quiet Pasta']);

    $this->getJson('/api/v1/restaurants/filter-test/menu-items?featured=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nebula Burger');

    $this->getJson("/api/v1/restaurants/filter-test/menu-items?category_id={$drinks->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Iced Tea');

    $this->getJson('/api/v1/restaurants/filter-test/menu-items?search=Nebula')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Nebula Burger');
});

test('menu items cannot be read through the wrong restaurant slug', function () {
    $first = Restaurant::factory()->create(['slug' => 'first-spot']);
    $second = Restaurant::factory()->create(['slug' => 'second-spot']);
    $category = Category::factory()->for($first)->create();
    $item = MenuItem::factory()->for($first)->for($category)->create();

    $this->getJson("/api/v1/restaurants/second-spot/menu-items/{$item->id}")->assertNotFound();
    $this->getJson("/api/v1/restaurants/first-spot/menu-items/{$item->id}")->assertOk();
});

test('an unavailable item detail returns 404', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'detail-spot']);
    $category = Category::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($restaurant)->for($category)->unavailable()->create();

    $this->getJson("/api/v1/restaurants/detail-spot/menu-items/{$item->id}")->assertNotFound();
});

test('restaurant and menu listings paginate', function () {
    Restaurant::factory()->count(3)->create();

    $this->getJson('/api/v1/restaurants?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.total', 3);
});
