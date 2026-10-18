<?php

use App\Enums\FoodRestriction;
use App\Enums\FoodRestrictionKind;
use App\Models\Category;
use App\Models\CustomerFoodRestriction;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;

test('registering saves normalised allergy and restriction rows', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [
            ['restriction' => 'peanuts'],
            ['restriction' => 'celiac-disease'],
            ['restriction' => 'vegan'],
            ['restriction' => 'other-allergy', 'custom_label' => 'Mustard'],
        ],
    ])->assertCreated();

    $user = User::query()->where('email', 'ada@example.test')->sole();
    $rows = $user->foodRestrictions()->get()->keyBy(fn ($row) => $row->restriction->value);

    expect($rows)->toHaveCount(4)
        ->and($rows['peanuts']->kind)->toBe(FoodRestrictionKind::Allergy)
        ->and($rows['celiac-disease']->kind)->toBe(FoodRestrictionKind::DietaryRestriction)
        ->and($rows['vegan']->kind)->toBe(FoodRestrictionKind::DietaryPreference)
        ->and($rows['other-allergy']->custom_label)->toBe('Mustard');
});

test('registering with no restrictions writes no placeholder row', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'None Chosen',
        'email' => 'none@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [],
    ])->assertCreated();

    expect(CustomerFoodRestriction::query()->count())->toBe(0);
});

test('the web registration form saves restrictions too', function () {
    $this->post(route('register.store'), [
        'name' => 'Web User',
        'email' => 'web@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+66 812 345 678',
        'food_restrictions' => [
            ['restriction' => 'shellfish'],
            ['restriction' => 'keto'],
        ],
    ]);

    $user = User::query()->where('email', 'web@example.test')->sole();

    expect($user->phone)->toBe('+66 812 345 678')
        ->and($user->foodRestrictions()->pluck('restriction')->map->value->all())
        ->toEqualCanonicalizing(['shellfish', 'keto']);
});

test('the same restriction cannot be saved twice', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Dupe',
        'email' => 'dupe@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [
            ['restriction' => 'peanuts'],
            ['restriction' => 'peanuts'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('food_restrictions.1.restriction');

    expect(User::query()->where('email', 'dupe@example.test')->exists())->toBeFalse();
});

test('an "Other" entry requires the customer to describe it', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Other',
        'email' => 'other@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [
            ['restriction' => 'other-intolerance'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('food_restrictions.0.custom_label');
});

test('a fixed entry cannot carry a custom label', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Label',
        'email' => 'label@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [
            ['restriction' => 'milk', 'custom_label' => 'Lactose'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('food_restrictions.0.custom_label');
});

test('an unknown restriction is rejected', function () {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Bogus',
        'email' => 'bogus@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'food_restrictions' => [
            ['restriction' => 'gluten-free'],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('food_restrictions.0.restriction');
});

test('a customer can replace their own restrictions and clear them', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/v1/auth/me/food-restrictions', [
            'food_restrictions' => [
                ['restriction' => 'peanuts'],
                ['restriction' => 'vegetarian'],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.food_restrictions.0.restriction', 'peanuts');

    expect($user->foodRestrictions()->count())->toBe(2);

    $this->actingAs($user)
        ->putJson('/api/v1/auth/me/food-restrictions', [
            'food_restrictions' => [['restriction' => 'milk']],
        ])
        ->assertOk();

    expect($user->foodRestrictions()->pluck('restriction')->map->value->all())
        ->toBe(['milk']);

    $this->actingAs($user)
        ->putJson('/api/v1/auth/me/food-restrictions', ['food_restrictions' => []])
        ->assertOk();

    expect($user->foodRestrictions()->count())->toBe(0);
});

test('restrictions cannot be edited through another customer id', function () {
    $victim = User::factory()->create();
    $attacker = User::factory()->create();

    CustomerFoodRestriction::factory()->for($victim)->create([
        'restriction' => FoodRestriction::Peanuts,
        'kind' => FoodRestrictionKind::Allergy,
    ]);

    $this->actingAs($attacker)
        ->putJson('/api/v1/auth/me/food-restrictions', [
            'user_id' => $victim->id,
            'food_restrictions' => [],
        ])
        ->assertOk();

    // The victim's rows are untouched: the endpoint only writes the caller.
    expect($victim->foodRestrictions()->count())->toBe(1)
        ->and($attacker->foodRestrictions()->count())->toBe(0);
});

test('guests cannot read or write restrictions', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->putJson('/api/v1/auth/me/food-restrictions', ['food_restrictions' => []])
        ->assertUnauthorized();
});

test('a customer can open their restriction settings page', function () {
    $user = User::factory()->create();

    CustomerFoodRestriction::factory()->for($user)->create([
        'restriction' => FoodRestriction::Shellfish,
        'kind' => FoodRestrictionKind::Allergy,
    ]);

    $this->actingAs($user)
        ->get(route('account.restrictions.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customer/Restrictions')
            ->has('catalog.allergies')
            ->has('saved', 1)
            ->where('saved.0.restriction', 'shellfish')
        );
});

test('guests are redirected from the restriction settings page', function () {
    $this->get(route('account.restrictions.edit'))->assertRedirect(route('login'));
});

test('the restriction vocabulary matches the documented options', function () {
    $catalog = FoodRestriction::catalog();
    $values = array_column(
        array_merge(...array_values($catalog)),
        'value',
    );

    expect($values)->toContain(
        'peanuts', 'tree-nuts', 'shellfish', 'crustaceans', 'fish', 'eggs',
        'milk', 'soy', 'wheat', 'sesame', 'other-allergy',
        'gluten-free-requirement', 'celiac-disease', 'gluten-sensitivity',
        'lactose-intolerance', 'other-intolerance', 'other-dietary-restriction',
        'vegetarian', 'vegan', 'keto',
    );
});

test("a customer's saved restrictions reach the dish page and match the dish", function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slug' => 'mori-thai-kitchen']);
    $category = Category::factory()->for($restaurant)->create();

    $dish = MenuItem::factory()->for($restaurant)->for($category)->create([
        'name' => 'Chicken Satay',
        'slug' => 'chicken-satay',
        'allergens' => ['peanuts', 'wheat'],
    ]);

    $customer = User::factory()->create();
    CustomerFoodRestriction::factory()->for($customer)->create([
        'restriction' => FoodRestriction::Peanuts,
        'kind' => FoodRestrictionKind::Allergy,
    ]);

    $response = $this->actingAs($customer)->get(route('menu.show', $dish->slug));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Dish')
            ->has('auth.foodRestrictions', 1)
            ->where('auth.foodRestrictions.0.restriction', 'peanuts')
            ->where('auth.foodRestrictions.0.allergen', 'peanuts')
        );
});

test('a guest receives no restriction data for the dish page', function () {
    $restaurant = Restaurant::factory()->openDaily()->create(['slug' => 'mori-thai-kitchen']);
    $category = Category::factory()->for($restaurant)->create();

    MenuItem::factory()->for($restaurant)->for($category)->create(['slug' => 'pad-thai']);

    $this->get(route('menu.show', 'pad-thai'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.foodRestrictions', []));
});
