<?php

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('the accounts page provides filtered users and their staff type as Inertia props', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create([
        'name' => 'Kai Kitchen',
        'email' => 'kai@example.test',
    ]);
    $restaurant = Restaurant::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create([
        'staff_type' => StaffType::Kitchen,
    ]);

    $this->actingAs($superAdmin)
        ->get('/admin/users?role=STAFF&search=Kai')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->where('filters.role', 'STAFF')
            ->where('filters.search', 'Kai')
            ->where('users.data.0.id', $staff->id)
            ->where('users.data.0.staff_type', StaffType::Kitchen->value)
            ->where('users.meta.total', 1)
        );
});

test('super admins can assign customer accounts to staff or administrator', function (UserRole $role) {
    $superAdmin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();

    $this->from('/admin/users')
        ->actingAs($superAdmin)
        ->patch(route('admin.accounts.role.update', $customer), ['role' => $role->value])
        ->assertRedirect('/admin/users')
        ->assertSessionHas('success', "{$customer->name} is now ".match ($role) {
            UserRole::Staff => 'Staff',
            UserRole::Admin => 'Administrator',
            default => 'Customer',
        }.'.');

    expect($customer->fresh()->role)->toBe($role);
})->with([
    'staff' => UserRole::Staff,
    'administrator' => UserRole::Admin,
]);

test('role changes save the selected staff type on existing memberships', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $customer = User::factory()->create();
    $restaurant = Restaurant::factory()->create();
    $membership = RestaurantMember::factory()->for($restaurant)->for($customer)->create([
        'role' => MembershipRole::Staff,
        'staff_type' => StaffType::Cashier,
    ]);

    $this->from('/admin/users')
        ->actingAs($superAdmin)
        ->patch(route('admin.accounts.role.update', $customer), [
            'role' => UserRole::Staff->value,
            'staff_type' => StaffType::Waiter->value,
        ])
        ->assertRedirect('/admin/users');

    expect($customer->fresh()->role)->toBe(UserRole::Staff)
        ->and($membership->fresh()->staff_type)->toBe(StaffType::Waiter);
});

test('staff type can be cleared from existing memberships', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $staff = User::factory()->staff()->create();
    $restaurant = Restaurant::factory()->create();
    $membership = RestaurantMember::factory()->for($restaurant)->for($staff)->create([
        'staff_type' => StaffType::Cashier,
    ]);

    $this->from('/admin/users')
        ->actingAs($superAdmin)
        ->patch(route('admin.accounts.role.update', $staff), [
            'role' => 'staff',
            'staff_type' => null,
        ])
        ->assertRedirect('/admin/users');

    expect($membership->fresh()->staff_type)->toBeNull();
});

test('admin staff and customer accounts get 403 on both access endpoints', function (UserRole $actorRole) {
    $actor = User::factory()->create(['role' => $actorRole]);
    $target = User::factory()->admin()->create();

    $this->actingAs($actor)
        ->from('/admin/users')
        ->patch(route('admin.accounts.role.update', $target), ['role' => UserRole::Staff->value])
        ->assertForbidden();

    $this->actingAs($actor)
        ->from('/admin/users')
        ->post(route('admin.accounts.remove-access', $target))
        ->assertForbidden();
})->with([
    'administrator' => UserRole::Admin,
    'staff' => UserRole::Staff,
    'customer' => UserRole::Customer,
]);

test('super admin accounts cannot be changed or have access removed', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();
    $restaurant = Restaurant::factory()->create();
    $membership = RestaurantMember::factory()->for($restaurant)->for($target)->create();

    $this->actingAs($actor)
        ->from('/admin/users')
        ->patch(route('admin.accounts.role.update', $target), ['role' => UserRole::Customer->value])
        ->assertForbidden();

    $this->actingAs($actor)
        ->from('/admin/users')
        ->post(route('admin.accounts.remove-access', $target))
        ->assertForbidden();

    expect($target->fresh()->role)->toBe(UserRole::SuperAdmin)
        ->and($membership->fresh())->not->toBeNull();
});

test('super admin is rejected as a requested target role', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->patchJson(route('admin.accounts.role.update', $target), ['role' => 'super_admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($target->fresh()->role)->toBe(UserRole::Customer);
});

test('staff type is rejected when assigning a non-staff role', function () {
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->patchJson(route('admin.accounts.role.update', $target), [
            'role' => 'admin',
            'staff_type' => 'cashier',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_type');

    expect($target->fresh()->role)->toBe(UserRole::Customer);
});

test('remove access resets staff and administrator accounts and deletes their memberships and database sessions', function (UserRole $role) {
    config()->set('session.driver', 'database');
    $actor = User::factory()->superAdmin()->create();
    $target = User::factory()->create(['role' => $role]);
    $restaurant = Restaurant::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($target)->create();
    DB::table('sessions')->insert([
        'id' => "target-session-{$target->id}",
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'test',
        'last_activity' => time(),
    ]);

    $this->actingAs($actor)
        ->from('/admin/users')
        ->post(route('admin.accounts.remove-access', $target))
        ->assertRedirect('/admin/users')
        ->assertSessionHas('success', "Access removed. {$target->name} is now a Customer.");

    expect($target->fresh()->role)->toBe(UserRole::Customer);
    $this->assertDatabaseMissing('restaurant_members', ['user_id' => $target->id]);
    $this->assertDatabaseMissing('sessions', ['id' => "target-session-{$target->id}"]);
})->with([
    'staff' => UserRole::Staff,
    'administrator' => UserRole::Admin,
]);
