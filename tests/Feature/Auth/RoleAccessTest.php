<?php

use App\Enums\UserRole;
use App\Models\CustomerFoodRestriction;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use App\Support\HomePath;
use Database\Seeders\DemoAccountSeeder;
use Illuminate\Support\Facades\Hash;

test('each documented demo role exists with the expected platform role', function () {
    $expectations = [
        'superadmin@mori.demo' => UserRole::SuperAdmin,
        'admin@mori.demo' => UserRole::Admin,
        'staff@mori.demo' => UserRole::Staff,
        'customer@mori.demo' => UserRole::Customer,
    ];

    $this->seed(DemoAccountSeeder::class);

    foreach ($expectations as $email => $role) {
        expect(User::query()->where('email', $email)->sole()->role)->toBe($role);
    }
});

test('demo account passwords match the documentation', function () {
    $this->seed(DemoAccountSeeder::class);

    $credentials = [
        'superadmin@mori.demo' => 'MoriSuperAdmin123!',
        'admin@mori.demo' => 'MoriAdmin123!',
        'staff@mori.demo' => 'MoriStaff123!',
        'customer@mori.demo' => 'MoriCustomer123!',
    ];

    foreach ($credentials as $email => $password) {
        $user = User::query()->where('email', $email)->sole();

        expect(Hash::check($password, $user->password))->toBeTrue();
    }
});

test('re-seeding demo accounts does not reset a changed password or duplicate rows', function () {
    $this->seed(DemoAccountSeeder::class);

    $customer = User::query()->where('email', 'customer@mori.demo')->sole();
    $customer->update(['password' => Hash::make('changed-by-the-developer')]);

    $counts = [
        User::query()->count(),
        CustomerFoodRestriction::query()->count(),
    ];

    $this->seed(DemoAccountSeeder::class);

    expect(Hash::check('changed-by-the-developer', $customer->fresh()->password))->toBeTrue()
        ->and([
            User::query()->count(),
            CustomerFoodRestriction::query()->count(),
        ])->toBe($counts);
});

test('the demo customer has example allergies and a dietary preference', function () {
    $this->seed(DemoAccountSeeder::class);

    $restrictions = User::query()
        ->where('email', 'customer@mori.demo')
        ->sole()
        ->foodRestrictions()
        ->pluck('restriction')
        ->map->value
        ->all();

    expect($restrictions)->toContain('peanuts', 'shellfish', 'vegetarian');
});

test('the demo seeder refuses to run in production', function () {
    $original = app()->environment();

    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->seed(DemoAccountSeeder::class);

        expect(User::query()->where('email', 'like', '%@mori.demo')->exists())->toBeFalse();
    } finally {
        app()->detectEnvironment(fn (): string => $original);
    }
});

test('a super admin can list users but an admin and staff cannot', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    $this->actingAs($super)->getJson('/api/v1/admin/users')->assertOk();
    $this->actingAs($admin)->getJson('/api/v1/admin/users')->assertForbidden();
    $this->actingAs($staff)->getJson('/api/v1/admin/users')->assertForbidden();
});

test('only a super admin may change a user role', function () {
    $target = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $staff = User::factory()->staff()->create();

    foreach ([$admin, $staff] as $actor) {
        $this->actingAs($actor)
            ->patchJson("/api/v1/admin/users/{$target->id}/role", ['role' => UserRole::Admin->value])
            ->assertForbidden();
    }

    expect($target->fresh()->role)->toBe(UserRole::Customer);
});

test('a super admin can promote a customer without granting more than requested', function () {
    $super = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($super)
        ->patchJson("/api/v1/admin/users/{$target->id}/role", ['role' => UserRole::Admin->value])
        ->assertOk()
        ->assertJsonPath('data.role', UserRole::Admin->value);

    expect($target->fresh()->role)->toBe(UserRole::Admin);
});

test('a super admin cannot change the last super admin account', function () {
    $super = User::factory()->superAdmin()->create();

    $this->actingAs($super)
        ->patchJson("/api/v1/admin/users/{$super->id}/role", ['role' => UserRole::Customer->value])
        ->assertForbidden();

    expect($super->fresh()->role)->toBe(UserRole::SuperAdmin);
});

test('a super admin cannot change another super admin account', function () {
    $actor = User::factory()->superAdmin()->create();
    $other = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();

    $this->actingAs($actor)
        ->patchJson("/api/v1/admin/users/{$target->id}/role", ['role' => UserRole::Admin->value])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(UserRole::SuperAdmin)
        ->and($other->fresh()->role)->toBe(UserRole::SuperAdmin);
});

test('a super admin cannot demote their own account', function () {
    $actor = User::factory()->superAdmin()->create();
    User::factory()->superAdmin()->create();

    $this->actingAs($actor)
        ->patchJson("/api/v1/admin/users/{$actor->id}/role", ['role' => UserRole::Admin->value])
        ->assertForbidden();

    expect($actor->fresh()->role)->toBe(UserRole::SuperAdmin);
});

test('registration always creates a customer and ignores a requested privileged role', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::SuperAdmin->value,
    ])->assertCreated()->assertJsonPath('data.role', UserRole::Customer->value);

    expect(User::query()->where('email', 'mallory@example.test')->sole()->role)
        ->toBe(UserRole::Customer);
});

test('the web registration form also ignores a posted role', function () {
    $this->post(route('register.store'), [
        'name' => 'Eve',
        'email' => 'eve@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::Admin->value,
    ]);

    expect(User::query()->where('email', 'eve@example.test')->sole()->role)
        ->toBe(UserRole::Customer);
});

test('customers only see their own account and orders', function () {
    $customer = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($customer)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $customer->id);

    $this->actingAs($customer)
        ->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => UserRole::Admin->value])
        ->assertForbidden();

    expect($other->fresh()->role)->toBe(UserRole::Customer);
});

test('login sends each role to a page it can open', function () {
    $restaurant = Restaurant::factory()->create();

    $admin = User::factory()->admin()->create();
    RestaurantMember::factory()->admin()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $admin->id,
    ]);
    $customer = User::factory()->create();

    expect(HomePath::forUser($admin))->toBe('/admin')
        ->and(HomePath::forUser($customer))->toBe('/orders')
        ->and(HomePath::forUser(null))->toBe('/');
});

test('the login page renders in the storefront design', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('the registration page receives the restriction vocabulary', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('auth/Register')
            ->has('foodRestrictionCatalog.allergies')
            ->has('foodRestrictionCatalog.dietary_restrictions')
            ->has('foodRestrictionCatalog.dietary_preferences')
        );
});

test('the platform accounts page renders for a super admin only', function () {
    $super = User::factory()->superAdmin()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($super)
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('canAssignRoles', true)
        );

    $this->actingAs($admin)->get('/admin/users')->assertForbidden();
    $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
});

test('the platform accounts page requires a signed-in user', function () {
    $this->get('/admin/users')->assertRedirect(route('login'));
});
