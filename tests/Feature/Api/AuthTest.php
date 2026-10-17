<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guests cannot reach authenticated endpoints', function () {
    $this->getJson('/api/v1/orders')->assertUnauthorized();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('registration creates a customer account and signs the user in', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ada@example.test')
        ->assertJsonPath('data.role', UserRole::Customer->value);

    $user = User::query()->where('email', 'ada@example.test')->firstOrFail();

    expect($user->role)->toBe(UserRole::Customer);
    $this->assertAuthenticatedAs($user);
});

test('registration ignores a client supplied role', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Mallory',
        'email' => 'mallory@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::SuperAdmin->value,
    ]);

    $response->assertCreated()->assertJsonPath('data.role', UserRole::Customer->value);
    expect(User::query()->where('email', 'mallory@example.test')->firstOrFail()->role)
        ->toBe(UserRole::Customer);
});

test('login returns the authenticated profile and logout invalidates it', function () {
    $user = User::factory()->create([
        'email' => 'cook@example.test',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'cook@example.test',
        'password' => 'secret-password',
    ])->assertOk()->assertJsonPath('data.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

test('login rejects bad credentials without revealing which field was wrong', function () {
    User::factory()->create([
        'email' => 'cook@example.test',
        'password' => Hash::make('secret-password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'cook@example.test',
        'password' => 'wrong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

test('authenticated users can read and update their own profile only', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);

    $this->actingAs($user)
        ->patchJson('/api/v1/auth/me', ['name' => 'Renamed', 'email' => $user->email])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');

    expect($other->fresh()->name)->not->toBe('Renamed')
        ->and($user->fresh()->role)->toBe(UserRole::Customer);
});
