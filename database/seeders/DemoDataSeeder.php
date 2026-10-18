<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Enums\MembershipRole;
use App\Enums\MenuCourse;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\StaffType;
use App\Enums\TableShape;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Models\RestaurantMember;
use App\Models\User;
use App\Services\OrderNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Development/demo data. Every write is keyed on a natural key so running the
 * seeder twice does not duplicate rows, and nothing here touches production.
 *
 * All credentials are fake development fixtures: password = "password".
 */
class DemoDataSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, email: string, role: UserRole, no_show_count?: int}>
     */
    private array $people = [
        'super' => ['name' => 'Sam Superadmin', 'email' => 'super.admin@example.test', 'role' => UserRole::SuperAdmin],
        'admin' => ['name' => 'Alex Admin', 'email' => 'admin@example.test', 'role' => UserRole::Admin],
        'ember_manager' => ['name' => 'Mia Manager', 'email' => 'ember.manager@example.test', 'role' => UserRole::Staff],
        'ember_cashier' => ['name' => 'Carl Cashier', 'email' => 'ember.cashier@example.test', 'role' => UserRole::Staff],
        'ember_kitchen' => ['name' => 'Kai Kitchen', 'email' => 'ember.kitchen@example.test', 'role' => UserRole::Staff],
        'ember_driver' => ['name' => 'Dana Driver', 'email' => 'ember.driver@example.test', 'role' => UserRole::Staff],
        'saffron_manager' => ['name' => 'Priya Manager', 'email' => 'saffron.manager@example.test', 'role' => UserRole::Staff],
        'alice' => ['name' => 'Alice Customer', 'email' => 'alice@example.test', 'role' => UserRole::Customer, 'no_show_count' => 1],
        'bob' => ['name' => 'Bob Customer', 'email' => 'bob@example.test', 'role' => UserRole::Customer],
    ];

    /**
     * @var array<string, array{name: string, slug: string, address: string, phone: string, tax_rate: float, delivery_fee: float, min_delivery_amount: float, auto_confirm: bool}>
     */
    private array $restaurants = [
        'ember' => [
            'name' => 'Ember & Oak',
            'slug' => 'ember-and-oak',
            'address' => '14 Kindling Street, Riverside',
            'phone' => '555-0100',
            'tax_rate' => 0.1000,
            'delivery_fee' => 3.50,
            'min_delivery_amount' => 15.00,
            'auto_confirm' => true,
        ],
        'saffron' => [
            'name' => 'Saffron House',
            'slug' => 'saffron-house',
            'address' => '88 Spice Lane, Old Town',
            'phone' => '555-0200',
            'tax_rate' => 0.0800,
            'delivery_fee' => 4.00,
            'min_delivery_amount' => 20.00,
            'auto_confirm' => false,
        ],
    ];

    /**
     * @var array<string, array<int, array{name: string, items: array<int, array{name: string, price: float, description: string, featured?: bool}>}>>
     */
    private array $menus = [
        'ember' => [
            ['name' => 'Starters', 'items' => [
                ['name' => 'Charred Sourdough', 'price' => 6.50, 'description' => 'Wood-fired bread, smoked butter, sea salt.'],
                ['name' => 'Ash-Roasted Beetroot', 'price' => 9.00, 'description' => 'Beetroot, walnut crumb, goat curd.', 'featured' => true],
            ]],
            ['name' => 'Mains', 'items' => [
                ['name' => 'Oak-Fired Chicken', 'price' => 22.00, 'description' => 'Half chicken, burnt lemon, thyme jus.', 'featured' => true],
                ['name' => 'Ember Burger', 'price' => 17.50, 'description' => 'Dry-aged beef, smoked cheddar, house pickles.'],
                ['name' => 'Charred Cauliflower Steak', 'price' => 15.00, 'description' => 'Romesco, pine nuts, burnt herb oil.'],
            ]],
            ['name' => 'Desserts', 'items' => [
                ['name' => 'Burnt Honey Tart', 'price' => 8.00, 'description' => 'Honey custard, flaked salt, crème fraîche.'],
            ]],
        ],
        'saffron' => [
            ['name' => 'Small Plates', 'items' => [
                ['name' => 'Saffron Arancini', 'price' => 8.50, 'description' => 'Saffron risotto, mozzarella, tomato jam.', 'featured' => true],
                ['name' => 'Cumin Flatbread', 'price' => 5.00, 'description' => 'Griddled flatbread, cumin butter.'],
            ]],
            ['name' => 'Curries', 'items' => [
                ['name' => 'Lamb Rogan Josh', 'price' => 19.50, 'description' => 'Slow lamb, kashmiri chilli, yoghurt.', 'featured' => true],
                ['name' => 'Paneer Butter Masala', 'price' => 16.00, 'description' => 'House paneer, tomato, fenugreek.'],
                ['name' => 'Dal Saffron', 'price' => 13.50, 'description' => 'Black lentils, 24 hour simmer.'],
            ]],
            ['name' => 'Sides', 'items' => [
                ['name' => 'Basmati Rice', 'price' => 3.50, 'description' => 'Steamed basmati, fried onion.'],
            ]],
        ],
    ];

    /**
     * @var array<string, array<int, array{label: string, capacity: int, min_capacity: int, pos_x: int, pos_y: int, shape: TableShape}>>
     */
    private array $tables = [
        'ember' => [
            ['label' => 'E1', 'capacity' => 2, 'min_capacity' => 1, 'pos_x' => 120, 'pos_y' => 140, 'shape' => TableShape::Round],
            ['label' => 'E2', 'capacity' => 4, 'min_capacity' => 2, 'pos_x' => 320, 'pos_y' => 140, 'shape' => TableShape::Square],
            ['label' => 'E3', 'capacity' => 6, 'min_capacity' => 4, 'pos_x' => 560, 'pos_y' => 160, 'shape' => TableShape::Rect],
            ['label' => 'E4', 'capacity' => 8, 'min_capacity' => 6, 'pos_x' => 360, 'pos_y' => 420, 'shape' => TableShape::Rect],
        ],
        'saffron' => [
            ['label' => 'S1', 'capacity' => 2, 'min_capacity' => 1, 'pos_x' => 140, 'pos_y' => 180, 'shape' => TableShape::Round],
            ['label' => 'S2', 'capacity' => 4, 'min_capacity' => 2, 'pos_x' => 380, 'pos_y' => 180, 'shape' => TableShape::Square],
            ['label' => 'S3', 'capacity' => 6, 'min_capacity' => 4, 'pos_x' => 620, 'pos_y' => 200, 'shape' => TableShape::Rect],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $people = $this->seedPeople();
            $restaurants = $this->seedRestaurants();
            $menus = $this->seedMenus($restaurants);
            $tables = $this->seedTables($restaurants);

            $this->seedMemberships($restaurants, $people);
            $this->seedReservations($restaurants, $tables, $people);
            $this->seedOrders($restaurants, $menus, $people);
        });
    }

    /**
     * @return array<string, User>
     */
    private function seedPeople(): array
    {
        $users = [];

        foreach ($this->people as $key => $attributes) {
            $users[$key] = User::query()->updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'role' => $attributes['role'],
                    'no_show_count' => $attributes['no_show_count'] ?? 0,
                    'email_verified_at' => now(),
                    'password' => 'password',
                ],
            );
        }

        return $users;
    }

    /**
     * @return array<string, Restaurant>
     */
    private function seedRestaurants(): array
    {
        $restaurants = [];

        foreach ($this->restaurants as $key => $attributes) {
            $restaurants[$key] = Restaurant::query()->updateOrCreate(
                ['slug' => $attributes['slug']],
                [
                    'name' => $attributes['name'],
                    'address' => $attributes['address'],
                    'phone' => $attributes['phone'],
                    'opening_hours' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
                        ->mapWithKeys(fn (string $day): array => [$day => ['open' => '11:30', 'close' => '22:30']])
                        ->all(),
                    'slot_minutes' => 90,
                    'auto_confirm_reservations' => $attributes['auto_confirm'],
                    'delivery_fee' => $attributes['delivery_fee'],
                    'min_delivery_amount' => $attributes['min_delivery_amount'],
                    'tax_rate' => $attributes['tax_rate'],
                    'is_active' => true,
                ],
            );
        }

        return $restaurants;
    }

    /**
     * @param  array<string, Restaurant>  $restaurants
     * @return array<string, array<string, MenuItem>>
     */
    private function seedMenus(array $restaurants): array
    {
        $items = [];

        foreach ($this->menus as $restaurantKey => $categories) {
            $restaurant = $restaurants[$restaurantKey];

            foreach (array_values($categories) as $index => $categoryData) {
                $category = Category::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $categoryData['name']],
                    ['sort_order' => $index],
                );

                foreach ($categoryData['items'] as $itemData) {
                    $items[$restaurantKey][$itemData['name']] = MenuItem::query()->updateOrCreate(
                        ['restaurant_id' => $restaurant->id, 'name' => $itemData['name']],
                        [
                            'category_id' => $category->id,
                            'course' => $this->courseForCategory($categoryData['name']),
                            'description' => $itemData['description'],
                            'price' => $itemData['price'],
                            'is_available' => true,
                            'is_featured' => $itemData['featured'] ?? false,
                        ],
                    );
                }
            }
        }

        return $items;
    }

    /**
     * Demo categories use free-form names, so the course is inferred from the
     * category the dish belongs to. New menu work sets the course explicitly.
     */
    private function courseForCategory(string $category): MenuCourse
    {
        return match (true) {
            str_contains($category, 'Dessert') => MenuCourse::Dessert,
            str_contains($category, 'Starter'),
            str_contains($category, 'Small Plate'),
            str_contains($category, 'Side') => MenuCourse::Starter,
            default => MenuCourse::Main,
        };
    }

    /**
     * @param  array<string, Restaurant>  $restaurants
     * @return array<string, array<string, DiningTable>>
     */
    private function seedTables(array $restaurants): array
    {
        $tables = [];

        foreach ($this->tables as $restaurantKey => $rows) {
            $restaurant = $restaurants[$restaurantKey];

            foreach ($rows as $row) {
                $tables[$restaurantKey][$row['label']] = DiningTable::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'label' => $row['label']],
                    [
                        'capacity' => $row['capacity'],
                        'min_capacity' => $row['min_capacity'],
                        'pos_x' => $row['pos_x'],
                        'pos_y' => $row['pos_y'],
                        'shape' => $row['shape'],
                        'is_active' => true,
                    ],
                );
            }
        }

        return $tables;
    }

    /**
     * @param  array<string, Restaurant>  $restaurants
     * @param  array<string, User>  $people
     */
    private function seedMemberships(array $restaurants, array $people): void
    {
        $memberships = [
            ['restaurant' => 'ember', 'user' => 'ember_manager', 'role' => MembershipRole::Admin, 'staff_type' => null],
            ['restaurant' => 'ember', 'user' => 'ember_cashier', 'role' => MembershipRole::Staff, 'staff_type' => StaffType::Cashier],
            ['restaurant' => 'ember', 'user' => 'ember_kitchen', 'role' => MembershipRole::Staff, 'staff_type' => StaffType::Kitchen],
            ['restaurant' => 'ember', 'user' => 'ember_driver', 'role' => MembershipRole::Staff, 'staff_type' => StaffType::Driver],
            ['restaurant' => 'saffron', 'user' => 'saffron_manager', 'role' => MembershipRole::Admin, 'staff_type' => null],
        ];

        foreach ($memberships as $membership) {
            RestaurantMember::query()->updateOrCreate(
                [
                    'restaurant_id' => $restaurants[$membership['restaurant']]->id,
                    'user_id' => $people[$membership['user']]->id,
                ],
                ['role' => $membership['role'], 'staff_type' => $membership['staff_type']],
            );
        }
    }

    /**
     * @param  array<string, Restaurant>  $restaurants
     * @param  array<string, array<string, DiningTable>>  $tables
     * @param  array<string, User>  $people
     */
    private function seedReservations(array $restaurants, array $tables, array $people): void
    {
        $reservations = [
            [
                'restaurant' => 'ember', 'table' => 'E2', 'customer' => 'alice',
                'party_size' => 2, 'days_ahead' => 2, 'time' => '19:00',
                'status' => ReservationStatus::Confirmed, 'request' => 'Window seat if possible.',
            ],
            [
                'restaurant' => 'ember', 'table' => 'E3', 'customer' => 'bob',
                'party_size' => 5, 'days_ahead' => 3, 'time' => '20:00',
                'status' => ReservationStatus::Confirmed, 'request' => null,
            ],
            [
                'restaurant' => 'saffron', 'table' => 'S1', 'customer' => 'alice',
                'party_size' => 2, 'days_ahead' => 4, 'time' => '18:30',
                'status' => ReservationStatus::Pending, 'request' => 'Anniversary dinner.',
            ],
        ];

        foreach ($reservations as $data) {
            $restaurant = $restaurants[$data['restaurant']];
            $table = $tables[$data['restaurant']][$data['table']];
            $customer = $people[$data['customer']];

            $start = now()->addDays($data['days_ahead'])->setTimeFromTimeString($data['time']);

            $reservation = Reservation::query()->firstOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'table_id' => $table->id,
                    'customer_id' => $customer->id,
                    'start_time' => $start,
                ],
                [
                    'party_size' => $data['party_size'],
                    'end_time' => $start->copy()->addMinutes($restaurant->slot_minutes),
                    'status' => $data['status'],
                    'special_request' => $data['request'],
                    'hold_expires_at' => $data['status'] === ReservationStatus::Pending
                        ? now()->addMinutes(15)
                        : null,
                ],
            );

            $reservation->statusHistory()->firstOrCreate(
                ['to_status' => $data['status']->value],
                [
                    'from_status' => null,
                    'changed_by' => $customer->id,
                    'changed_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  array<string, Restaurant>  $restaurants
     * @param  array<string, array<string, MenuItem>>  $menus
     * @param  array<string, User>  $people
     */
    private function seedOrders(array $restaurants, array $menus, array $people): void
    {
        $this->seedPickupOrder(
            $restaurants['ember'],
            [['item' => $menus['ember']['Oak-Fired Chicken'], 'quantity' => 1], ['item' => $menus['ember']['Charred Sourdough'], 'quantity' => 2]],
            $people['alice'],
        );

        $this->seedDeliveryOrder(
            $restaurants['ember'],
            [['item' => $menus['ember']['Ember Burger'], 'quantity' => 2], ['item' => $menus['ember']['Burnt Honey Tart'], 'quantity' => 1]],
            $people['bob'],
            $people['ember_driver'],
        );

        $this->seedPickupOrder(
            $restaurants['saffron'],
            [['item' => $menus['saffron']['Lamb Rogan Josh'], 'quantity' => 1], ['item' => $menus['saffron']['Basmati Rice'], 'quantity' => 2]],
            $people['bob'],
        );
    }

    /**
     * @param  array<int, array{item: MenuItem, quantity: int}>  $lines
     */
    private function seedPickupOrder(Restaurant $restaurant, array $lines, User $customer): void
    {
        $order = $this->findOrCreateOrder($restaurant, $customer, OrderType::Pickup, OrderStatus::Completed);

        foreach ($lines as $line) {
            $this->addLine($order, $line['item'], $line['quantity']);
        }

        $this->persistOrderTotals($order);
        $this->seedHistory($order, [
            [null, OrderStatus::Placed],
            [OrderStatus::Placed, OrderStatus::Confirmed],
            [OrderStatus::Confirmed, OrderStatus::Preparing],
            [OrderStatus::Preparing, OrderStatus::Ready],
            [OrderStatus::Ready, OrderStatus::Completed],
        ]);

        if ($order->payments()->doesntExist()) {
            $order->payments()->create([
                'method' => PaymentMethod::Cash,
                'amount' => $order->total,
                'status' => PaymentStatus::Paid,
                'provider_ref' => null,
                'paid_at' => now()->subHours(2),
            ]);
        }

        $order->update(['payment_status' => PaymentStatus::Paid]);
    }

    /**
     * @param  array<int, array{item: MenuItem, quantity: int}>  $lines
     */
    private function seedDeliveryOrder(Restaurant $restaurant, array $lines, User $customer, User $driver): void
    {
        $order = $this->findOrCreateOrder($restaurant, $customer, OrderType::Delivery, OrderStatus::OutForDelivery);

        foreach ($lines as $line) {
            $this->addLine($order, $line['item'], $line['quantity']);
        }

        $this->persistOrderTotals($order);

        $this->seedHistory($order, [
            [null, OrderStatus::Placed],
            [OrderStatus::Placed, OrderStatus::Confirmed],
            [OrderStatus::Confirmed, OrderStatus::Preparing],
            [OrderStatus::Preparing, OrderStatus::Ready],
            [OrderStatus::Ready, OrderStatus::OutForDelivery],
        ]);

        $order->deliveryDetail()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'driver_id' => $driver->id,
                'recipient_name' => $customer->name,
                'recipient_phone' => '555-0199',
                'address_line' => '22 Customer Crescent, Riverside',
                'latitude' => 51.5074000,
                'longitude' => -0.1278000,
                'delivery_status' => DeliveryStatus::PickedUp,
                'assigned_at' => now()->subMinutes(20),
                'picked_up_at' => now()->subMinutes(5),
                'delivered_at' => null,
                'failure_reason' => null,
            ],
        );

        if ($order->payments()->doesntExist()) {
            $order->payments()->create([
                'method' => PaymentMethod::Card,
                'amount' => $order->total,
                'status' => PaymentStatus::Paid,
                'provider_ref' => 'dev-demo-card-001',
                'paid_at' => now()->subMinutes(30),
            ]);
        }

        $order->update(['payment_status' => PaymentStatus::Paid]);
    }

    private function findOrCreateOrder(Restaurant $restaurant, User $customer, OrderType $type, OrderStatus $status): Order
    {
        $existing = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->where('customer_id', $customer->id)
            ->where('order_type', $type)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return Order::query()->create([
            'restaurant_id' => $restaurant->id,
            'customer_id' => $customer->id,
            'order_number' => app(OrderNumberGenerator::class)->generate(),
            'order_type' => $type,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'customer_note' => 'Demo order created by DemoDataSeeder.',
        ]);
    }

    private function addLine(Order $order, MenuItem $item, int $quantity): void
    {
        $order->items()->firstOrCreate(
            ['menu_item_id' => $item->id],
            [
                'item_name_snapshot' => $item->name,
                'unit_price' => $item->price,
                'quantity' => $quantity,
                'line_total' => round((float) $item->price * $quantity, 2),
                'note' => null,
            ],
        );
    }

    private function persistOrderTotals(Order $order): void
    {
        $subtotal = (float) $order->items()->sum('line_total');
        $deliveryFee = $order->order_type === OrderType::Delivery
            ? (float) $order->restaurant->delivery_fee
            : 0.0;
        $tax = round($subtotal * (float) $order->restaurant->tax_rate, 2);

        $order->update([
            'subtotal' => $subtotal,
            'delivery_fee' => $deliveryFee,
            'tax' => $tax,
            'discount' => 0,
            'total' => round($subtotal + $deliveryFee + $tax, 2),
        ]);

        $order->refresh();
    }

    /**
     * @param  array<int, array{0: OrderStatus|null, 1: OrderStatus}>  $steps
     */
    private function seedHistory(Order $order, array $steps): void
    {
        foreach ($steps as $index => [$from, $to]) {
            $order->statusHistory()->firstOrCreate(
                ['to_status' => $to->value],
                [
                    'from_status' => $from?->value,
                    'changed_by' => $order->customer_id,
                    'changed_at' => now()->subMinutes((count($steps) - $index) * 10),
                    'reason' => null,
                ],
            );
        }
    }
}
