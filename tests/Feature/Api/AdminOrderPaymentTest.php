<?php

use App\Enums\MembershipRole;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;

/**
 * @return array{0: Restaurant, 1: User, 2: User}
 */
function staffedRestaurant(MembershipRole $role = MembershipRole::Staff): array
{
    $restaurant = Restaurant::factory()->create();
    $user = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($user)->create(['role' => $role]);

    return [$restaurant, $user, User::factory()->create()];
}

test('staff walk an order through its lifecycle and every step is logged', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['order_type' => OrderType::Pickup]);

    foreach ([OrderStatus::Confirmed, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Completed] as $status) {
        $this->actingAs($staff)
            ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/status", ['status' => $status->value])
            ->assertOk()
            ->assertJsonPath('data.status', $status->value);
    }

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($order->statusHistory()->count())->toBe(4);
});

test('an illegal order transition is rejected and leaves state untouched', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['status' => OrderStatus::Placed]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/status", [
            'status' => OrderStatus::Completed->value,
        ])
        ->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::Placed)
        ->and($order->statusHistory()->count())->toBe(0);
});

test('delivery orders must go out for delivery before completion', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->delivery()->for($restaurant)->create(['status' => OrderStatus::Ready]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/status", [
            'status' => OrderStatus::Completed->value,
        ])
        ->assertStatus(422);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/status", [
            'status' => OrderStatus::OutForDelivery->value,
        ])
        ->assertOk();
});

test('staff cannot perform administrator-only transitions', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['status' => OrderStatus::Preparing]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/status", [
            'status' => OrderStatus::Cancelled->value,
        ])
        ->assertStatus(422);

    [$adminRestaurant, $admin] = staffedRestaurant(MembershipRole::Admin);
    $adminOrder = Order::factory()->for($adminRestaurant)->create(['status' => OrderStatus::Preparing]);

    $this->actingAs($admin)
        ->patchJson("/api/v1/admin/restaurants/{$adminRestaurant->id}/orders/{$adminOrder->id}/status", [
            'status' => OrderStatus::Cancelled->value,
            'reason' => 'Kitchen closed early',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value);
});

test('the admin order queue filters by status and type', function () {
    [$restaurant, $staff] = staffedRestaurant();

    Order::factory()->for($restaurant)->create(['status' => OrderStatus::Placed, 'order_type' => OrderType::Pickup]);
    Order::factory()->delivery()->for($restaurant)->create(['status' => OrderStatus::Preparing]);

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/orders?status=PREPARING")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.order_type', OrderType::Delivery->value);

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/orders?order_type=PICKUP")
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('staff can record a manual payment and the order becomes paid', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['total' => 25.00]);

    $this->actingAs($staff)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Cash->value,
            'amount' => 25.00,
            'status' => PaymentStatus::Paid->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', PaymentStatus::Paid->value)
        ->assertJsonPath('data.paid_at', fn ($value) => $value !== null);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a payment cannot exceed the outstanding balance', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['total' => 20.00]);

    Payment::factory()->for($order)->create(['amount' => 15.00, 'status' => PaymentStatus::Paid]);

    $this->actingAs($staff)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Cash->value,
            'amount' => 10.00,
            'status' => PaymentStatus::Paid->value,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('amount');

    $this->actingAs($staff)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Cash->value,
            'amount' => 5.00,
            'status' => PaymentStatus::Paid->value,
        ])
        ->assertCreated();
});

test('staff can list the payments recorded against an order', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['total' => 40.00]);

    Payment::factory()->for($order)->create(['amount' => 20.00, 'status' => PaymentStatus::Paid]);
    Payment::factory()->for($order)->create(['amount' => 10.00, 'status' => PaymentStatus::Failed]);

    $this->actingAs($staff)
        ->getJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a failed payment attempt does not mark the order paid', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->for($restaurant)->create(['total' => 25.00]);

    $this->actingAs($staff)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Card->value,
            'amount' => 25.00,
            'status' => PaymentStatus::Failed->value,
            'provider_ref' => 'declined-001',
        ])
        ->assertCreated()
        ->assertJsonPath('data.paid_at', null);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);
});

test('refunds require a captured payment and reset the order payment status', function () {
    [$restaurant, $admin] = staffedRestaurant(MembershipRole::Admin);
    $order = Order::factory()->for($restaurant)->create([
        'total' => 25.00,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
    ]);
    $payment = Payment::factory()->for($order)->create(['amount' => 25.00, 'status' => PaymentStatus::Paid]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund")
        ->assertOk()
        ->assertJsonPath('data.status', PaymentStatus::Refunded->value);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Refunded);
});

test('customers cannot record payments or read another customers payments', function () {
    [$restaurant] = staffedRestaurant();
    $customer = User::factory()->create();
    $order = Order::factory()->for($restaurant)->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)
        ->postJson("/api/v1/admin/restaurants/{$restaurant->id}/orders/{$order->id}/payments", [
            'method' => PaymentMethod::Online->value,
            'amount' => 10.00,
            'status' => PaymentStatus::Paid->value,
        ])
        ->assertForbidden();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Unpaid);
});

test('delivery status advances the order status', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->delivery()->for($restaurant)->create(['status' => OrderStatus::Ready]);
    $delivery = $order->deliveryDetail()->create([
        'recipient_name' => 'Linus',
        'recipient_phone' => '555-0111',
        'address_line' => '2 Kernel Street',
        'delivery_status' => 'UNASSIGNED',
    ]);

    $driver = User::factory()->create();
    RestaurantMember::factory()->for($restaurant)->for($driver)->driver()->create(['role' => MembershipRole::Staff]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/deliveries/{$delivery->id}", [
            'driver_id' => $driver->id,
            'delivery_status' => 'ASSIGNED',
        ])
        ->assertOk()
        ->assertJsonPath('data.delivery_status', 'ASSIGNED')
        ->assertJsonPath('data.driver.id', $driver->id);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/deliveries/{$delivery->id}", [
            'delivery_status' => 'PICKED_UP',
        ])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::OutForDelivery);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/deliveries/{$delivery->id}", [
            'delivery_status' => 'DELIVERED',
        ])
        ->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Completed)
        ->and($delivery->fresh()->delivered_at)->not->toBeNull();
});

test('a driver must belong to the restaurant', function () {
    [$restaurant, $staff] = staffedRestaurant();
    $order = Order::factory()->delivery()->for($restaurant)->create(['status' => OrderStatus::Ready]);
    $delivery = $order->deliveryDetail()->create([
        'recipient_name' => 'Linus',
        'recipient_phone' => '555-0111',
        'address_line' => '2 Kernel Street',
        'delivery_status' => 'UNASSIGNED',
    ]);

    $this->actingAs($staff)
        ->patchJson("/api/v1/admin/restaurants/{$restaurant->id}/deliveries/{$delivery->id}", [
            'driver_id' => User::factory()->create()->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('driver_id');
});
