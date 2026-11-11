<?php

use App\Enums\MembershipRole;
use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('non-members cannot reach restaurant administration endpoints', function () {
    $restaurant = Restaurant::factory()->create();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/orders")
        ->assertForbidden();

    $this->actingAs($outsider)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/kpis")
        ->assertForbidden();
});

test('staff members cannot manage the menu or members', function () {
    $restaurant = Restaurant::factory()->create();
    $staff = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create(['role' => MembershipRole::Staff]);

    $this->actingAs($staff)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/categories", ['name' => 'Drinks'])
        ->assertForbidden();

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/members")
        ->assertForbidden();

    $this->actingAs($staff)
        ->post("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/image", [
            'image' => UploadedFile::fake()->createWithContent(
                'dish.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/yRoAAAAASUVORK5CYII=') ?: '',
            ),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
});

test('restaurant admins can upload menu images to local storage and persist their relative paths', function () {
    Storage::fake('public', ['url' => 'https://local.test/storage']);

    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $response = $this->actingAs($admin)
        ->post("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/image", [
            'image' => UploadedFile::fake()->createWithContent(
                'dish.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/yRoAAAAASUVORK5CYII=') ?: '',
            ),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $directory = "restaurants/{$restaurant->id}/menu";
    $paths = Storage::disk('public')->allFiles($directory);

    expect($paths)->toHaveCount(1)
        ->and($response->json('image_url'))->toBe(Storage::disk('public')->url($paths[0]));

    $category = Category::factory()->for($restaurant)->create();
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Uploaded Dish',
            'price' => 9.5,
            'image_url' => $response->json('image_url'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.image_url', Storage::disk('public')->url($paths[0]));

    $this->assertDatabaseHas('menu_items', [
        'restaurant_id' => $restaurant->id,
        'image_url' => $paths[0],
    ]);
});

test('restaurant admins use cloud storage for menu images in production', function () {
    Storage::fake('public', ['url' => 'https://local.test/storage']);
    Storage::fake('private', ['url' => 'https://cloud.test']);
    config(['filesystems.default' => 'private']);
    app()->detectEnvironment(fn (): string => 'production');

    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $response = $this->actingAs($admin)
        ->post("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/image", [
            'image' => UploadedFile::fake()->createWithContent(
                'dish.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/yRoAAAAASUVORK5CYII=') ?: '',
            ),
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $paths = Storage::disk('private')->allFiles("restaurants/{$restaurant->id}/menu");
    expect($paths)->toHaveCount(1)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and($response->json('image_url'))->toContain('expiration=');

    $category = Category::factory()->for($restaurant)->create();
    $savedItemResponse = $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Cloud Dish',
            'price' => 11,
            'image_url' => $response->json('image_url'),
        ])
        ->assertCreated();

    expect($savedItemResponse->json('data.image_url'))->toContain('expiration=');

    $this->assertDatabaseHas('menu_items', [
        'restaurant_id' => $restaurant->id,
        'image_url' => $paths[0],
    ]);

    $externalImageUrl = 'https://images.example.test/dish.jpg';
    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'External Image Dish',
            'price' => 12,
            'image_url' => $externalImageUrl,
        ])
        ->assertCreated()
        ->assertJsonPath('data.image_url', $externalImageUrl);

    $this->assertDatabaseHas('menu_items', ['image_url' => $externalImageUrl]);
});

test('replacing and deleting a menu image uses the selected storage disk', function () {
    Storage::fake('public', ['url' => 'https://local.test/storage']);

    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();
    $directory = "restaurants/{$restaurant->id}/menu";
    $oldPath = "{$directory}/old.png";
    $newPath = "{$directory}/new.png";
    Storage::disk('public')->put($oldPath, 'old image');
    Storage::disk('public')->put($newPath, 'new image');
    $menuItem = MenuItem::factory()->for($restaurant)->for($category)->create(['image_url' => $oldPath]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$menuItem->id}", [
            'image_url' => Storage::disk('public')->url($newPath),
        ])
        ->assertOk()
        ->assertJsonPath('data.image_url', Storage::disk('public')->url($newPath));

    expect(Storage::disk('public')->exists($oldPath))->toBeFalse()
        ->and(Storage::disk('public')->exists($newPath))->toBeTrue();
    $this->assertDatabaseHas('menu_items', ['id' => $menuItem->id, 'image_url' => $newPath]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$menuItem->id}")
        ->assertNoContent();

    expect(Storage::disk('public')->exists($newPath))->toBeFalse();
});

test('production menu image replacement and deletion use cloud storage', function () {
    Storage::fake('public', ['url' => 'https://local.test/storage']);
    Storage::fake('private', ['url' => 'https://cloud.test']);
    config(['filesystems.default' => 'private']);
    app()->detectEnvironment(fn (): string => 'production');

    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();
    $directory = "restaurants/{$restaurant->id}/menu";
    $oldPath = "{$directory}/old.png";
    $newPath = "{$directory}/new.png";
    Storage::disk('private')->put($oldPath, 'old cloud image');
    Storage::disk('private')->put($newPath, 'new cloud image');
    Storage::disk('public')->put($oldPath, 'local image');
    $menuItem = MenuItem::factory()->for($restaurant)->for($category)->create(['image_url' => $oldPath]);

    $replacementResponse = $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$menuItem->id}", [
            'image_url' => Storage::disk('private')->temporaryUrl($newPath, now()->addMinutes(30)),
        ])
        ->assertOk();

    expect($replacementResponse->json('data.image_url'))->toContain('expiration=');

    expect(Storage::disk('private')->exists($oldPath))->toBeFalse()
        ->and(Storage::disk('public')->exists($oldPath))->toBeTrue();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$menuItem->id}")
        ->assertNoContent();

    expect(Storage::disk('private')->exists($newPath))->toBeFalse();
});

test('menu image uploads reject files that are not supported images', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->post("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/image", [
            'image' => UploadedFile::fake()->create('dish.txt', 5, 'text/plain'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('image');
});

test('restaurant admins can create categories and menu items scoped to their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $category = $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/categories", ['name' => 'Starters', 'sort_order' => 1])
        ->assertCreated()
        ->json('data');

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category['id'],
            'name' => 'Soup of the Day',
            'price' => 6.5,
        ])
        ->assertCreated()
        ->assertJsonPath('data.restaurant_id', $restaurant->id)
        ->assertJsonPath('data.category.name', 'Starters');
});

test('adding a seventh signature replaces the oldest signature for that restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $signatures = collect(range(0, 5))->map(fn (int $index): MenuItem => MenuItem::factory()
        ->for($restaurant)
        ->for($category)
        ->featured()
        ->create([
            'created_at' => now()->subDays(6 - $index),
            'updated_at' => now()->subDays(6 - $index),
        ]));
    $oldestSignature = $signatures->first();

    $newSignature = $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'New Signature Dish',
            'price' => 18,
            'is_featured' => true,
        ])
        ->assertCreated()
        ->json('data');

    expect($oldestSignature->fresh()->is_featured)->toBeFalse()
        ->and($restaurant->menuItems()->where('is_featured', true)->count())->toBe(6)
        ->and(MenuItem::query()->findOrFail($newSignature['id'])->is_featured)->toBeTrue();

    $nextOldest = $signatures->get(1);
    $unfeatured = MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$unfeatured->id}", [
            'is_featured' => true,
        ])
        ->assertOk();

    expect($nextOldest->fresh()->is_featured)->toBeFalse()
        ->and($restaurant->menuItems()->where('is_featured', true)->count())->toBe(6);
});

test('restaurant admins can save menu customization groups', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Custom Noodles',
            'price' => 12,
            'customization_groups' => [[
                'id' => 'heat',
                'name' => 'Spice level',
                'type' => 'radio',
                'options' => [
                    ['id' => 'mild', 'name' => 'Mild', 'price' => 0],
                    ['id' => 'hot', 'name' => 'Hot', 'price' => 1.5],
                ],
            ]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.customization_groups.0.type', 'radio')
        ->assertJsonPath('data.customization_groups.0.options.1.name', 'Hot');
});

test('menu customization groups reject unsupported selection modes', function () {
    $restaurant = Restaurant::factory()->create();
    $category = Category::factory()->for($restaurant)->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $category->id,
            'name' => 'Custom Noodles',
            'price' => 12,
            'customization_groups' => [[
                'id' => 'heat',
                'name' => 'Spice level',
                'type' => 'dropdown',
                'options' => [['id' => 'mild', 'name' => 'Mild', 'price' => 0]],
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customization_groups.0.type');
});

test('a menu item cannot be assigned to another restaurant category', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();
    $foreignCategory = Category::factory()->for($otherRestaurant)->create();

    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items", [
            'category_id' => $foreignCategory->id,
            'name' => 'Stolen Menu Item',
            'price' => 9.99,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category_id');
});

test('cross-restaurant ids return 404 rather than leaking data', function () {
    $mine = Restaurant::factory()->create();
    $theirs = Restaurant::factory()->create();

    $admin = User::factory()->create();
    RestaurantMember::factory()->for($mine)->for($admin)->admin()->create();

    $category = Category::factory()->for($theirs)->create();
    $table = DiningTable::factory()->for($theirs)->create();
    $order = Order::factory()->for($theirs)->create();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$mine->id}/categories/{$category->id}", ['name' => 'Hijacked'])
        ->assertNotFound();

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$mine->id}/tables/{$table->id}", ['capacity' => 2])
        ->assertNotFound();

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$mine->id}/orders/{$order->id}")
        ->assertNotFound();

    expect($category->fresh()->name)->not->toBe('Hijacked');
});

test('category deletion is refused while menu items still reference it', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $category = Category::factory()->for($restaurant)->create();
    MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/restaurants/{$restaurant->id}/categories/{$category->id}")
        ->assertStatus(409);

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
});

test('deleting a menu item soft deletes it so order history survives', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $category = Category::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/restaurants/{$restaurant->id}/menu-items/{$item->id}")
        ->assertNoContent();

    expect(MenuItem::query()->whereKey($item->id)->exists())->toBeFalse()
        ->and(MenuItem::withTrashed()->whereKey($item->id)->exists())->toBeTrue();
});

test('a table with reservations is deactivated instead of deleted', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    $table = DiningTable::factory()->for($restaurant)->create();

    Reservation::factory()
        ->for($restaurant)
        ->for(User::factory(), 'customer')
        ->create(['table_id' => $table->id]);

    $this->actingAs($admin)
        ->deleteJson("/api/v1/admin/restaurants/{$restaurant->id}/tables/{$table->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(DiningTable::query()->whereKey($table->id)->exists())->toBeTrue();
});

test('only platform administrators can create restaurants and toggle activation', function () {
    $restaurant = Restaurant::factory()->create();
    $restaurantAdmin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($restaurantAdmin)->admin()->create();

    $this->actingAs($restaurantAdmin)
        ->postJson('/api/v1/admin/restaurants', ['name' => 'New Spot'])
        ->assertForbidden();

    $this->actingAs($restaurantAdmin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/deactivate")
        ->assertForbidden();

    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->postJson('/api/v1/admin/restaurants', ['name' => 'New Spot'])
        ->assertCreated();

    $this->actingAs($superAdmin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

test('restaurant admins can add staff members and staff roles are validated', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();
    $candidate = User::factory()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/members", [
            'user_id' => $candidate->id,
            'role' => MembershipRole::Staff->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $candidate->id);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/members", [
            'user_id' => $candidate->id,
            'role' => MembershipRole::Staff->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

test('staff see the operational order queue scoped to their restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $other = Restaurant::factory()->create();
    $staff = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create(['role' => MembershipRole::Staff]);

    $visible = Order::factory()->for($restaurant)->create(['status' => OrderStatus::Placed]);
    Order::factory()->for($other)->create(['status' => OrderStatus::Placed]);

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/orders?active=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});
