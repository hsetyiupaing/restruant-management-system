<?php

use App\Enums\Allergen;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

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

test('public menu items expose their configured ordering options', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'options-menu']);
    $category = Category::factory()->for($restaurant)->create();
    MenuItem::factory()->for($restaurant)->for($category)->create([
        'name' => 'Custom Bowl',
        'customization_groups' => [[
            'id' => 'toppings',
            'name' => 'Add toppings',
            'type' => 'checkbox',
            'options' => [['id' => 'chili', 'name' => 'Chili oil', 'price' => 1.5]],
        ]],
    ]);

    $this->getJson('/api/v1/restaurants/options-menu/menu-items')
        ->assertOk()
        ->assertJsonPath('data.0.customization_groups.0.name', 'Add toppings')
        ->assertJsonPath('data.0.customization_groups.0.options.0.name', 'Chili oil');
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

test('a menu item can be fetched by its slug as well as its id', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'slug-spot']);
    $category = Category::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($restaurant)->for($category)->create([
        'name' => 'Pad Thai',
        'slug' => 'pad-thai',
    ]);

    $this->getJson('/api/v1/restaurants/slug-spot/menu-items/pad-thai')
        ->assertOk()
        ->assertJsonPath('data.slug', 'pad-thai')
        ->assertJsonPath('data.name', 'Pad Thai');

    // Numeric ids keep working for existing clients.
    $this->getJson("/api/v1/restaurants/slug-spot/menu-items/{$item->id}")
        ->assertOk()
        ->assertJsonPath('data.slug', 'pad-thai');
});

test('menu items expose recorded ingredients and allergens honestly', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'allergen-spot']);
    $category = Category::factory()->for($restaurant)->create();

    $recorded = MenuItem::factory()->for($restaurant)->for($category)->create([
        'slug' => 'peanut-noodles',
        'ingredients' => 'Rice noodles, peanuts, fish sauce.',
        'allergens' => [Allergen::Peanuts, Allergen::Fish],
    ]);

    $unrecorded = MenuItem::factory()->for($restaurant)->for($category)->create([
        'slug' => 'mystery-dish',
        'ingredients' => null,
        'allergens' => null,
    ]);

    $this->getJson("/api/v1/restaurants/allergen-spot/menu-items/{$recorded->slug}")
        ->assertOk()
        ->assertJsonPath('data.ingredients', 'Rice noodles, peanuts, fish sauce.')
        ->assertJsonPath('data.allergens', [
            ['value' => 'peanuts', 'label' => 'Peanuts'],
            ['value' => 'fish', 'label' => 'Fish'],
        ]);

    // Null is "not recorded", never "allergen free".
    $this->getJson("/api/v1/restaurants/allergen-spot/menu-items/{$unrecorded->slug}")
        ->assertOk()
        ->assertJsonPath('data.allergens', null)
        ->assertJsonPath('data.ingredients', null);
});

test('admin writes reject allergens outside the tracked list', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Mystery Dish',
            'price' => 100,
            'allergens' => ['peanuts', 'gluten'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('allergens.1');
});

test('the admin menu list matches the public menu for the same restaurant', function () {
    $restaurant = Restaurant::factory()->create(['slug' => 'parity-spot']);
    $category = Category::factory()->for($restaurant)->create(['name' => 'Mains']);
    $admin = User::factory()->admin()->create();
    RestaurantMember::factory()->admin()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $admin->id,
    ]);

    $live = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Green Curry']);
    $soldOut = MenuItem::factory()->for($restaurant)->for($category)->unavailable()->create(['name' => 'Sold Out Soup']);
    $removed = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Retired Dish']);
    $removed->delete();

    // Everything the customer can order is on both lists...
    $public = $this->getJson('/api/v1/restaurants/parity-spot/menu-items')->json('data');
    $adminList = $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items")
        ->assertOk()
        ->json('data');

    expect(collect($public)->pluck('id')->all())->toBe([$live->id])
        ->and(collect($adminList)->pluck('id')->sort()->values()->all())
        ->toBe(collect([$live->id, $soldOut->id])->sort()->values()->all());

    // ...and the soft-deleted dish is not presented as a live row.
    expect(collect($adminList)->pluck('name'))->not->toContain('Retired Dish');

    // It stays auditable behind an explicit filter.
    $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items?trashed=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Retired Dish');
});
