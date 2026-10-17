<?php

use App\Enums\MembershipRole;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

/**
 * A weekday two days from now at 18:00, always inside the lead-time window.
 */
function slotAt(int $daysAhead = 2): string
{
    return now()->addDays($daysAhead)->setTime(18, 0)->format('Y-m-d H:i:s');
}

test('availability lists every active table with a status', function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slug' => 'avail-spot', 'slot_minutes' => 90]);
    $free = DiningTable::factory()->for($restaurant)->create(['label' => 'T1', 'capacity' => 4]);
    $booked = DiningTable::factory()->for($restaurant)->create(['label' => 'T2', 'capacity' => 4]);
    DiningTable::factory()->for($restaurant)->inactive()->create(['label' => 'OLD']);

    Reservation::factory()->for($restaurant)->for(User::factory(), 'customer')->for($booked, 'table')->create([
        'start_time' => slotAt(),
        'end_time' => now()->addDays(2)->setTime(19, 30),
        'status' => ReservationStatus::Confirmed,
    ]);

    $date = now()->addDays(2)->format('Y-m-d');

    $response = $this->getJson("/api/v1/restaurants/avail-spot/availability?date={$date}&time=18:00&party_size=2")
        ->assertOk()
        ->assertJsonCount(2, 'data.tables')
        ->assertJsonPath('meta.available_count', 1);

    $statuses = collect($response->json('data.tables'))->pluck('status', 'label')->all();

    expect($statuses)->toMatchArray([
        'T1' => 'AVAILABLE',
        'T2' => 'RESERVED',
    ])->not->toHaveKey('OLD');
});

test('a table too small for the party is marked unavailable', function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slug' => 'tiny-spot']);
    DiningTable::factory()->for($restaurant)->create(['label' => 'Small', 'capacity' => 2, 'min_capacity' => 1]);

    $date = now()->addDays(2)->format('Y-m-d');

    $this->getJson("/api/v1/restaurants/tiny-spot/availability?date={$date}&time=18:00&party_size=6")
        ->assertOk()
        ->assertJsonPath('data.tables.0.status', 'UNAVAILABLE')
        ->assertJsonPath('meta.available_count', 0);
});

test('a customer can book a table within the lead window', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);
    $customer = User::factory()->create();

    $response = $this->actingAs($customer)->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => slotAt(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', ReservationStatus::Pending->value)
        ->assertJsonPath('data.table.id', $table->id);

    $reservation = Reservation::query()->firstOrFail();

    expect($reservation->customer_id)->toBe($customer->id)
        ->and($reservation->hold_expires_at)->not->toBeNull()
        ->and($reservation->statusHistory()->count())->toBe(1);
});

test('restaurants with auto-confirm confirm reservations immediately', function () {
    $restaurant = Restaurant::factory()->openDaily()->autoConfirm()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => slotAt(),
    ])->assertCreated()->assertJsonPath('data.status', ReservationStatus::Confirmed->value);
});

test('overlapping reservations on the same table are refused', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);

    Reservation::factory()->for($restaurant)->for(User::factory(), 'customer')->for($table, 'table')->create([
        'start_time' => slotAt(),
        'end_time' => now()->addDays(2)->setTime(19, 30),
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => now()->addDays(2)->setTime(18, 30)->format('Y-m-d H:i:s'),
    ])->assertStatus(409);

    expect(Reservation::query()->count())->toBe(1);
});

test('back to back reservations that do not overlap are allowed', function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slot_minutes' => 90]);
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);

    Reservation::factory()->for($restaurant)->for(User::factory(), 'customer')->for($table, 'table')->create([
        'start_time' => now()->addDays(2)->setTime(18, 0),
        'end_time' => now()->addDays(2)->setTime(19, 30),
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => now()->addDays(2)->setTime(19, 30)->format('Y-m-d H:i:s'),
    ])->assertCreated();
});

test('cancelled and expired reservations do not block a slot', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);

    Reservation::factory()->cancelled()->for($restaurant)->for(User::factory(), 'customer')->for($table, 'table')->create([
        'start_time' => slotAt(),
        'end_time' => now()->addDays(2)->setTime(19, 30),
    ]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => slotAt(),
    ])->assertCreated();
});

test('reservations outside opening hours are refused', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => now()->addDays(2)->setTime(3, 0)->format('Y-m-d H:i:s'),
    ])->assertStatus(422)->assertJsonValidationErrors('start_time');
});

test('reservations must respect lead time and the 30 day horizon', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);
    $customer = User::factory()->create();

    $this->actingAs($customer)->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => now()->addMinutes(15)->format('Y-m-d H:i:s'),
    ])->assertStatus(422)->assertJsonValidationErrors('start_time');

    $this->actingAs($customer)->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 2,
        'start_time' => now()->addDays(45)->setTime(18, 0)->format('Y-m-d H:i:s'),
    ])->assertStatus(422)->assertJsonValidationErrors('start_time');
});

test('a party larger than the table capacity is refused', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 2, 'min_capacity' => 1]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'party_size' => 5,
        'start_time' => slotAt(),
    ])->assertStatus(422)->assertJsonValidationErrors('party_size');
});

test('a table from another restaurant cannot be booked', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $other = Restaurant::factory()->openDaily()->create();
    $foreignTable = DiningTable::factory()->for($other)->create(['capacity' => 4]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/reservations', [
        'restaurant_id' => $restaurant->id,
        'table_id' => $foreignTable->id,
        'party_size' => 2,
        'start_time' => slotAt(),
    ])->assertNotFound();
});

test('customers may cancel a future reservation but not a near one', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);
    $customer = User::factory()->create();

    $future = Reservation::factory()->for($restaurant)->for($customer, 'customer')->for($table, 'table')->create([
        'start_time' => now()->addDays(3)->setTime(18, 0),
        'end_time' => now()->addDays(3)->setTime(19, 30),
    ]);

    $soon = Reservation::factory()->for($restaurant)->for($customer, 'customer')->for($table, 'table')->create([
        'start_time' => now()->addHour(),
        'end_time' => now()->addHours(2),
    ]);

    $this->actingAs($customer)->postJson("/api/v1/reservations/{$future->id}/cancel")->assertOk();
    $this->actingAs($customer)->postJson("/api/v1/reservations/{$soon->id}/cancel")->assertForbidden();
});

test('staff status transitions are validated and recorded', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);
    $staff = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create(['role' => MembershipRole::Staff]);

    $reservation = Reservation::factory()->for($restaurant)->for(User::factory(), 'customer')->for($table, 'table')->create([
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/reservations/{$reservation->id}/status", [
            'status' => ReservationStatus::Seated->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.status', ReservationStatus::Seated->value);

    // SEATED → PENDING is not a legal transition.
    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/reservations/{$reservation->id}/status", [
            'status' => ReservationStatus::Pending->value,
        ])
        ->assertStatus(422);
});

test('marking a no-show increments the customer no-show count', function () {
    $restaurant = Restaurant::factory()->openDaily()->create();
    $table = DiningTable::factory()->for($restaurant)->create(['capacity' => 4]);
    $customer = User::factory()->create(['no_show_count' => 0]);
    $staff = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create(['role' => MembershipRole::Staff]);

    $reservation = Reservation::factory()->for($restaurant)->for($customer, 'customer')->for($table, 'table')->create([
        'status' => ReservationStatus::Confirmed,
    ]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/reservations/{$reservation->id}/status", [
            'status' => ReservationStatus::NoShow->value,
        ])
        ->assertOk();

    expect($customer->fresh()->no_show_count)->toBe(1);
});

test('customers only see their own reservations', function () {
    $restaurant = Restaurant::factory()->create();
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $reservation = Reservation::factory()->for($restaurant)->for($mine, 'customer')->create();
    Reservation::factory()->for($restaurant)->for($theirs, 'customer')->create();

    $this->actingAs($mine)->getJson('/api/v1/reservations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $reservation->id);

    $this->actingAs($theirs)->getJson("/api/v1/reservations/{$reservation->id}")->assertForbidden();
});
