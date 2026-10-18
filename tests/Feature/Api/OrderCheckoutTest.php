<?php

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;

/**
 * @return array{restaurant: Restaurant, item: MenuItem, other: MenuItem}
 */
function checkoutFixture(): array
{
    $restaurant = Restaurant::factory()->create([
        'slug' => 'checkout-spot',
        'tax_rate' => 0.1000,
        'delivery_fee' => 3.50,
        'min_delivery_amount' => 15.00,
    ]);

    $category = Category::factory()->for($restaurant)->create();

    return [
        'restaurant' => $restaurant,
        'item' => MenuItem::factory()->for($restaurant)->for($category)->create(['price' => 10.00, 'name' => 'House Bowl']),
        'other' => MenuItem::factory()->for($restaurant)->for($category)->create(['price' => 5.00, 'name' => 'Side Salad']),
    ];
}

test('a pickup order is priced entirely from the database', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $customer = User::factory()->create();

    $response = $this->actingAs($customer)->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [
            ['menu_item_id' => $item->id, 'quantity' => 2],
        ],
        // Deliberately wrong client-supplied pricing: it must be ignored.
        'subtotal' => 0.01,
        'tax' => 0.01,
        'total' => 0.01,
        'payment_status' => PaymentStatus::Paid->value,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.order_type', OrderType::Pickup->value)
        ->assertJsonPath('data.status', OrderStatus::Placed->value)
        ->assertJsonPath('data.payment_status', PaymentStatus::Unpaid->value)
        ->assertJsonPath('data.totals.subtotal', '20.00')
        ->assertJsonPath('data.totals.tax', '2.00')
        ->assertJsonPath('data.totals.delivery_fee', '0.00')
        ->assertJsonPath('data.totals.total', '22.00')
        ->assertJsonPath('data.items.0.name', 'House Bowl')
        ->assertJsonPath('data.items.0.unit_price', '10.00');

    $order = Order::query()->where('customer_id', $customer->id)->firstOrFail();

    expect($order->payment_status)->toBe(PaymentStatus::Unpaid)
        ->and($order->order_number)->toMatch('/^ORD-\d{8}-[0-9A-Z]{8}$/')
        ->and($order->statusHistory()->count())->toBe(1);
});

test('selected dish options are priced and snapshotted from the menu configuration', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $customer = User::factory()->create();
    $item->update(['customization_groups' => [
        [
            'id' => 'size',
            'name' => 'Size',
            'type' => 'radio',
            'options' => [
                ['id' => 'regular', 'name' => 'Regular', 'price' => 0],
                ['id' => 'large', 'name' => 'Large', 'price' => 2.5],
            ],
        ],
        [
            'id' => 'extras',
            'name' => 'Extras',
            'type' => 'addons',
            'options' => [
                ['id' => 'egg', 'name' => 'Add egg', 'price' => 1],
            ],
        ],
    ]]);

    $response = $this->actingAs($customer)->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [[
            'menu_item_id' => $item->id,
            'quantity' => 2,
            'customizations' => [
                ['group_id' => 'size', 'option_ids' => ['large']],
                ['group_id' => 'extras', 'option_ids' => ['egg']],
            ],
        ]],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.items.0.unit_price', '13.50')
        ->assertJsonPath('data.items.0.line_total', '27.00')
        ->assertJsonPath('data.items.0.selected_customizations.0.group_name', 'Size')
        ->assertJsonPath('data.items.0.selected_customizations.0.options.0.name', 'Large')
        ->assertJsonPath('data.totals.subtotal', '27.00');
});

test('orders reject options that are not configured for the selected dish', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $item->update(['customization_groups' => [[
        'id' => 'size',
        'name' => 'Size',
        'type' => 'radio',
        'options' => [['id' => 'regular', 'name' => 'Regular', 'price' => 0]],
    ]]]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [[
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'customizations' => [
                ['group_id' => 'size', 'option_ids' => ['made-up-option']],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('orders reject the same customization group when it is submitted twice', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $item->update(['customization_groups' => [[
        'id' => 'size',
        'name' => 'Size',
        'type' => 'radio',
        'options' => [
            ['id' => 'regular', 'name' => 'Regular', 'price' => 0],
            ['id' => 'large', 'name' => 'Large', 'price' => 2],
        ],
    ]]]);

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [[
            'menu_item_id' => $item->id,
            'quantity' => 1,
            'customizations' => [
                ['group_id' => 'size', 'option_ids' => ['regular']],
                ['group_id' => 'size', 'option_ids' => ['large']],
            ],
        ]],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('a delivery order adds the restaurant delivery fee and stores delivery details', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $customer = User::factory()->create();

    $response = $this->actingAs($customer)->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Delivery->value,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
        'delivery' => [
            'recipient_name' => 'Grace Hopper',
            'recipient_phone' => '555-0100',
            'address_line' => '1 Compiler Way',
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.totals.subtotal', '20.00')
        ->assertJsonPath('data.totals.delivery_fee', '3.50')
        ->assertJsonPath('data.totals.total', '25.50')
        ->assertJsonPath('data.delivery.recipient_name', 'Grace Hopper')
        ->assertJsonPath('data.delivery.delivery_status', 'UNASSIGNED');
});

test('delivery orders below the minimum amount are rejected', function () {
    ['restaurant' => $restaurant, 'other' => $other] = checkoutFixture();
    $customer = User::factory()->create();

    $this->actingAs($customer)->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Delivery->value,
        'items' => [['menu_item_id' => $other->id, 'quantity' => 1]],
        'delivery' => [
            'recipient_name' => 'Grace Hopper',
            'recipient_phone' => '555-0100',
            'address_line' => '1 Compiler Way',
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('items');
});

test('delivery orders require delivery details', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Delivery->value,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 2]],
    ])->assertStatus(422)->assertJsonValidationErrors('delivery.recipient_name');
});

test('items from another restaurant cannot be ordered', function () {
    ['restaurant' => $restaurant] = checkoutFixture();

    $foreignRestaurant = Restaurant::factory()->create();
    $foreignCategory = Category::factory()->for($foreignRestaurant)->create();
    $foreignItem = MenuItem::factory()->for($foreignRestaurant)->for($foreignCategory)->create();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [['menu_item_id' => $foreignItem->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('items');

    expect(Order::query()->count())->toBe(0);
});

test('unavailable items cannot be ordered', function () {
    ['restaurant' => $restaurant] = checkoutFixture();
    $category = Category::factory()->for($restaurant)->create();
    $soldOut = MenuItem::factory()->for($restaurant)->for($category)->unavailable()->create();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [['menu_item_id' => $soldOut->id, 'quantity' => 1]],
    ])->assertStatus(422)->assertJsonValidationErrors('items');
});

test('invalid quantities are rejected before any order is written', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 0]],
    ])->assertStatus(422)->assertJsonValidationErrors('items.0.quantity');
});

test('inactive restaurants do not accept orders', function () {
    $restaurant = Restaurant::factory()->inactive()->create();
    $category = Category::factory()->for($restaurant)->create();
    $item = MenuItem::factory()->for($restaurant)->for($category)->create();

    $this->actingAs(User::factory()->create())->postJson('/api/v1/orders', [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ])->assertStatus(422);
});

test('an idempotency key prevents duplicate orders', function () {
    ['restaurant' => $restaurant, 'item' => $item] = checkoutFixture();
    $customer = User::factory()->create();

    $payload = [
        'restaurant_id' => $restaurant->id,
        'order_type' => OrderType::Pickup->value,
        'items' => [['menu_item_id' => $item->id, 'quantity' => 1]],
    ];

    $first = $this->actingAs($customer)
        ->withHeader('Idempotency-Key', 'checkout-abc')
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated();

    $second = $this->actingAs($customer)
        ->withHeader('Idempotency-Key', 'checkout-abc')
        ->postJson('/api/v1/orders', $payload)
        ->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Order::query()->count())->toBe(1);
});

test('customers only see their own orders', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();

    $restaurant = Restaurant::factory()->create();
    $myOrder = Order::factory()->for($restaurant)->for($mine, 'customer')->create();
    Order::factory()->for($restaurant)->for($theirs, 'customer')->create();

    $this->actingAs($mine)->getJson('/api/v1/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $myOrder->id);

    $this->actingAs($theirs)->getJson("/api/v1/orders/{$myOrder->id}")->assertForbidden();
});

test('a customer may cancel a placed order and the history is recorded', function () {
    $customer = User::factory()->create();
    $order = Order::factory()->for(User::factory()->create(), 'customer')
        ->for(Restaurant::factory())
        ->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)
        ->postJson("/api/v1/orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Cancelled->value)
        ->assertJsonPath('data.status_history.0.from_status', OrderStatus::Placed->value)
        ->assertJsonPath('data.status_history.0.to_status', OrderStatus::Cancelled->value);

    expect($order->fresh()->statusHistory()->count())->toBe(1);
});

test('a customer cannot cancel an order already in progress', function () {
    $customer = User::factory()->create();
    $order = Order::factory()
        ->for(Restaurant::factory())
        ->create(['customer_id' => $customer->id, 'status' => OrderStatus::Preparing]);

    $this->actingAs($customer)->postJson("/api/v1/orders/{$order->id}/cancel")->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});
