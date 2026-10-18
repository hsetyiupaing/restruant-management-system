<?php

use App\Enums\DietaryRequirement;
use App\Enums\MenuCourse;
use App\Models\Category;
use App\Models\DietaryTag;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

/**
 * @return array{0: Restaurant, 1: Category}
 */
function menuFixture(): array
{
    $restaurant = Restaurant::factory()->create(['slug' => 'filter-kitchen']);

    return [$restaurant, Category::factory()->for($restaurant)->create()];
}

beforeEach(function () {
    foreach (DietaryRequirement::cases() as $requirement) {
        DietaryTag::factory()->requirement($requirement)->create();
    }
});

test('the dietary tag catalogue is public and stable', function () {
    $this->getJson('/api/v1/dietary-tags')
        ->assertOk()
        ->assertJsonCount(count(DietaryRequirement::cases()), 'data')
        ->assertJsonFragment(['slug' => 'vegan', 'name' => 'Vegan'])
        ->assertJsonFragment(['slug' => 'keto', 'name' => 'Keto'])
        ->assertJsonMissing(['slug' => 'nut-allergy'])
        ->assertJsonMissing(['slug' => 'gluten-free'])
        ->assertJsonMissing(['slug' => 'lactose-intolerance']);
});

test('menu items expose their course and dietary tags', function () {
    [$restaurant, $category] = menuFixture();

    $item = MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Dessert)->create();
    $item->dietaryTags()->attach(DietaryTag::query()->whereIn('slug', ['vegan', 'keto'])->pluck('id'));

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items")
        ->assertOk()
        ->assertJsonPath('data.0.course', 'DESSERT')
        ->assertJsonCount(2, 'data.0.dietary_tags');
});

test('items can be filtered by course', function () {
    [$restaurant, $category] = menuFixture();

    MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Starter)->create(['name' => 'Rolls']);
    MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Main)->create(['name' => 'Curry']);
    MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Dessert)->create(['name' => 'Custard']);

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=DESSERT")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Custard');

    // Lowercase input is accepted.
    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=starter")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rolls');
});

test('an unknown course is ignored rather than returning nothing', function () {
    [$restaurant, $category] = menuFixture();

    MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=brunch")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('dietary filters require every selected tag, not any of them', function () {
    [$restaurant, $category] = menuFixture();

    $veganAndKeto = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Both']);
    $veganOnly = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Vegan only']);
    $ketoOnly = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Keto only']);

    $vegan = DietaryTag::query()->where('slug', 'vegan')->sole();
    $keto = DietaryTag::query()->where('slug', 'keto')->sole();

    $veganAndKeto->dietaryTags()->attach([$vegan->id, $keto->id]);
    $veganOnly->dietaryTags()->attach($vegan->id);
    $ketoOnly->dietaryTags()->attach($keto->id);

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?dietary=vegan,keto")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Both');
});

test('dietary filters accept a repeated query parameter too', function () {
    [$restaurant, $category] = menuFixture();

    $item = MenuItem::factory()->for($restaurant)->for($category)->create();
    $item->dietaryTags()->attach(
        DietaryTag::query()->whereIn('slug', ['keto', 'vegan'])->pluck('id'),
    );

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?dietary[]=keto&dietary[]=vegan")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('course and dietary filters combine with AND', function () {
    [$restaurant, $category] = menuFixture();

    $veganMain = MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Main)->create(['name' => 'Vegan curry']);
    $veganDessert = MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Dessert)->create(['name' => 'Vegan custard']);
    MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Main)->create(['name' => 'Beef stir-fry']);

    $vegan = DietaryTag::query()->where('slug', 'vegan')->sole();
    $veganMain->dietaryTags()->attach($vegan->id);
    $veganDessert->dietaryTags()->attach($vegan->id);

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=MAIN&dietary=vegan")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Vegan curry');
});

test('an unknown dietary slug is dropped instead of erroring', function () {
    [$restaurant, $category] = menuFixture();

    MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?dietary=halal")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a failed combination returns an empty result set, not an error', function () {
    [$restaurant, $category] = menuFixture();

    $item = MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Dessert)->create();
    $item->dietaryTags()->attach(DietaryTag::query()->where('slug', 'keto')->sole()->id);

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=STARTER&dietary=keto")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.total', 0);
});

test('unavailable dishes stay hidden even when they match the filters', function () {
    [$restaurant, $category] = menuFixture();

    $item = MenuItem::factory()->for($restaurant)->for($category)->course(MenuCourse::Main)->unavailable()->create();
    $item->dietaryTags()->attach(DietaryTag::query()->where('slug', 'vegan')->sole()->id);

    $this->getJson("/api/v1/restaurants/{$restaurant->slug}/menu-items?course=MAIN&dietary=vegan")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('the admin menu endpoint exposes and updates course and dietary tags', function () {
    [$restaurant, $category] = menuFixture();

    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $created = $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Vegan Green Curry',
            'course' => 'MAIN',
            'price' => 195,
            'dietary_tags' => ['vegan', 'keto'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.course', 'MAIN')
        ->assertJsonCount(2, 'data.dietary_tags')
        ->json('data');

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$created['id']}", [
            'dietary_tags' => ['vegan'],
        ])
        ->assertOk()
        ->assertJsonCount(1, 'data.dietary_tags')
        ->assertJsonPath('data.dietary_tags.0.slug', 'vegan');
});

test('an unknown dietary slug is rejected on write', function () {
    [$restaurant, $category] = menuFixture();

    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Mystery Dish',
            'price' => 100,
            'dietary_tags' => ['halal'],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('dietary_tags.0');
});

test('menu items get a slug that is unique inside their restaurant', function () {
    [$restaurant, $category] = menuFixture();

    $first = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Pad Thai', 'slug' => null]);
    $second = MenuItem::factory()->for($restaurant)->for($category)->create(['name' => 'Pad Thai', 'slug' => null]);

    expect($first->slug)->toBe('pad-thai')
        ->and($second->slug)->toBe('pad-thai-2');
});
