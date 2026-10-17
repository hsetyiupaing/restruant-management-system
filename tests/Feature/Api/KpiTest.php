<?php

use App\Enums\MembershipRole;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

test('kpis aggregate only the requested restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $other = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    Order::factory()->for($restaurant)->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 100.00,
        'order_type' => OrderType::Pickup,
    ]);
    Order::factory()->for($restaurant)->create([
        'status' => OrderStatus::Preparing,
        'payment_status' => PaymentStatus::Unpaid,
        'total' => 40.00,
        'order_type' => OrderType::Delivery,
    ]);
    Order::factory()->for($other)->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 999.00,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/kpis")
        ->assertOk()
        ->assertJsonPath('data.revenue', 100)
        ->assertJsonPath('data.order_count', 2)
        ->assertJsonPath('data.completed_order_count', 1)
        ->assertJsonPath('data.average_order_value', 100)
        ->assertJsonPath('data.pending_orders', 1)
        ->assertJsonPath('data.pickup_orders', 1)
        ->assertJsonPath('data.delivery_orders', 1);
});

test('cancelled, rejected and refunded orders never count as revenue', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    foreach ([OrderStatus::Cancelled, OrderStatus::Rejected, OrderStatus::Refunded] as $status) {
        Order::factory()->for($restaurant)->create([
            'status' => $status,
            'payment_status' => PaymentStatus::Paid,
            'total' => 50.00,
        ]);
    }

    $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/kpis")
        ->assertOk()
        ->assertJsonPath('data.revenue', 0)
        ->assertJsonPath('data.order_count', 3);
});

test('the kpi range is respected and series cover every day', function () {
    $restaurant = Restaurant::factory()->create();
    $admin = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($admin)->admin()->create();

    Order::factory()->for($restaurant)->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 30.00,
        'created_at' => now()->subDays(20),
    ]);
    Order::factory()->for($restaurant)->create([
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'total' => 70.00,
        'created_at' => now()->subDay(),
    ]);

    $from = now()->subDays(2)->toDateString();
    $to = now()->toDateString();

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/kpis?from={$from}&to={$to}")
        ->assertOk()
        ->assertJsonPath('data.range.from', $from)
        ->assertJsonPath('data.revenue', 70);

    expect($response->json('data.revenue_series'))->toHaveCount(3)
        ->and($response->json('data.order_series'))->toHaveCount(3);
});

test('staff without the admin membership cannot read kpis', function () {
    $restaurant = Restaurant::factory()->create();
    $staff = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($staff)->create(['role' => MembershipRole::Staff]);

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/kpis")
        ->assertForbidden();
});
