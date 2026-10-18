<?php

use App\Enums\DietaryRequirement;
use App\Enums\MenuCourse;
use App\Models\Category;
use App\Models\DietaryTag;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Database\Seeders\DietaryTagSeeder;
use Database\Seeders\ThaiMenuSeeder;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->seed(DietaryTagSeeder::class);
    $this->seed(ThaiMenuSeeder::class);

    $this->restaurant = Restaurant::query()->where('slug', 'mori-thai-kitchen')->sole();
});

test('the seeder creates the venue, three categories and at least 24 dishes', function () {
    expect(Category::query()->where('restaurant_id', $this->restaurant->id)->count())->toBe(3)
        ->and(MenuItem::query()->where('restaurant_id', $this->restaurant->id)->count())->toBeGreaterThanOrEqual(24);
});

test('every course is represented and correctly labelled', function () {
    $counts = MenuItem::query()
        ->where('restaurant_id', $this->restaurant->id)
        ->get()
        ->groupBy(fn (MenuItem $item): string => $item->course->value)
        ->map->count();

    expect($counts['STARTER'])->toBeGreaterThanOrEqual(8)
        ->and($counts['MAIN'])->toBeGreaterThanOrEqual(16)
        ->and($counts['DESSERT'])->toBeGreaterThanOrEqual(6);
});

test('every seeded dish has a slug, a positive price and a local image', function () {
    $items = MenuItem::query()->where('restaurant_id', $this->restaurant->id)->get();

    expect($items)->not->toBeEmpty();

    foreach ($items as $item) {
        expect($item->slug)->not->toBeEmpty()
            ->and((float) $item->price)->toBeGreaterThan(0)
            ->and($item->image_url)->toStartWith('/images/menu/')
            ->and(file_exists(public_path(ltrim($item->image_url, '/'))))->toBeTrue("Missing image file for {$item->name}");
    }
});

test('only the three dietary classifications exist and are attached to dishes', function () {
    expect(DietaryRequirement::cases())->toHaveCount(3)
        ->and(array_map(fn (DietaryRequirement $case): string => $case->value, DietaryRequirement::cases()))
        ->toBe(['vegetarian', 'vegan', 'keto'])
        ->and(DietaryTag::query()->count())->toBe(3)
        ->and(DietaryTag::query()->whereIn('slug', ['nut-allergy', 'gluten-free', 'lactose-intolerance'])->exists())->toBeFalse();

    $used = DietaryTag::query()->has('menuItems')->pluck('slug')->all();

    expect($used)->toContain('vegetarian', 'vegan', 'keto');
});

test('every seeded dish records its ingredients and allergens', function () {
    $items = MenuItem::query()->where('restaurant_id', $this->restaurant->id)->get();

    foreach ($items as $item) {
        expect($item->ingredients)->not->toBeEmpty()
            ->and($item->allergens)->toBeInstanceOf(Collection::class);
    }

    $satay = MenuItem::query()->where('slug', 'chicken-satay')->sole();
    $edamame = MenuItem::query()->where('slug', 'edamame')->sole();
    $fruit = MenuItem::query()->where('slug', 'fruit-platter')->sole();

    // Peanut sauce and sesame dressing are recorded as allergens, not inferred
    // from the absence of a dietary tag.
    expect($satay->allergens->map->value->all())
        ->toContain('peanuts', 'wheat');

    expect($edamame->allergens->map->value->all())
        ->toContain('soy', 'sesame');

    // A short list is not a safety claim; the platter genuinely lists none of
    // the tracked groups.
    expect($fruit->allergens->all())->toBe([]);
});

test('allergy and diet claims match the recipe instead of the dish name', function () {
    $satay = MenuItem::query()->where('slug', 'chicken-satay')->sole();
    $massaman = MenuItem::query()->where('slug', 'massaman-curry')->sole();
    $padThai = MenuItem::query()->where('slug', 'pad-thai')->sole();
    $edamame = MenuItem::query()->where('slug', 'edamame')->sole();
    $pineappleRice = MenuItem::query()->where('slug', 'pineapple-fried-rice')->sole();

    // Peanut-sauce and peanut-topped dishes record peanuts, and cashews are
    // recorded as tree nuts rather than being left off the list.
    foreach ([$satay, $massaman, $padThai] as $dish) {
        expect($dish->allergens->map->value->all())
            ->toContain('peanuts');
    }

    expect($pineappleRice->allergens->map->value->all())
        ->toContain('tree-nuts')
        ->not->toContain('peanuts');

    // The pork spring rolls are not vegetarian, while the egg- and fish-sauce-free
    // dumplings genuinely are vegan.
    expect(MenuItem::query()->where('slug', 'thai-spring-rolls')->sole()->dietaryTags->pluck('slug'))->not->toContain('vegetarian')
        ->and(MenuItem::query()->where('slug', 'vegetable-dumplings')->sole()->dietaryTags->pluck('slug'))->toContain('vegan');

    // Fish sauce and oyster sauce keep a dish off the vegan list.
    expect(MenuItem::query()->where('slug', 'vegetable-curry')->sole()->dietaryTags->pluck('slug'))->toContain('vegan')
        ->and(MenuItem::query()->where('slug', 'tofu-stir-fry')->sole()->dietaryTags->pluck('slug'))->toContain('vegan');

    // Plain steamed soy beans qualify.
    expect($edamame->dietaryTags->pluck('slug'))->toContain('vegan');
});

test('grilled chicken, fried chicken, bingsu and tiramisu are seeded as mains and desserts', function () {
    $grilled = MenuItem::query()->where('slug', 'grilled-chicken')->sole();
    $fried = MenuItem::query()->where('slug', 'fried-chicken')->sole();
    $bingsu = MenuItem::query()->where('slug', 'bingsu')->sole();
    $tiramisu = MenuItem::query()->where('slug', 'tiramisu')->sole();

    expect($grilled->course)->toBe(MenuCourse::Main)
        ->and($fried->course)->toBe(MenuCourse::Main)
        ->and($bingsu->course)->toBe(MenuCourse::Dessert)
        ->and($tiramisu->course)->toBe(MenuCourse::Dessert);

    // The butter finish is recorded as milk.
    expect($grilled->allergens->map->value->all())
        ->toContain('milk');

    // The buttermilk marinade and flour crust are recorded as milk and wheat.
    expect($fried->allergens->map->value->all())
        ->toContain('milk', 'wheat');

    // Mascarpone disqualifies tiramisu from vegan.
    expect($tiramisu->dietaryTags->pluck('slug'))->toContain('vegetarian')
        ->not->toContain('vegan');
});

test('the discontinued Thai desserts are gone from the menu', function () {
    expect(MenuItem::query()->whereIn('slug', ['pandan-custard', 'tub-tim-krob'])->exists())->toBeFalse();
});

test('only dishes built without carbohydrates are marked keto', function () {
    $keto = MenuItem::query()
        ->where('restaurant_id', $this->restaurant->id)
        ->has('dietaryTags')
        ->get()
        ->filter(fn (MenuItem $item): bool => $item->dietaryTags->contains('slug', 'keto'));

    expect($keto)->toHaveCount(1)
        ->and($keto->first()->slug)->toBe('cauliflower-rice-bowl');
});

test('running the seeder twice does not duplicate venues, categories or dishes', function () {
    $before = [
        Restaurant::query()->count(),
        Category::query()->where('restaurant_id', $this->restaurant->id)->count(),
        MenuItem::query()->where('restaurant_id', $this->restaurant->id)->count(),
        DB::table('dietary_tag_menu_item')->count(),
    ];

    $this->seed(ThaiMenuSeeder::class);

    $after = [
        Restaurant::query()->count(),
        Category::query()->where('restaurant_id', $this->restaurant->id)->count(),
        MenuItem::query()->where('restaurant_id', $this->restaurant->id)->count(),
        DB::table('dietary_tag_menu_item')->count(),
    ];

    expect($after)->toBe($before);
});

test('the storefront menu page renders the dietary vocabulary', function () {
    config(['restaurant.storefront_slug' => 'mori-thai-kitchen']);

    $this->get(route('menu'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Storefront/Menu')
            ->where('restaurant.slug', 'mori-thai-kitchen')
            ->has('dietaryTags', count(DietaryRequirement::cases()))
        );
});
