<?php

namespace Database\Seeders;

use App\Enums\Allergen;
use App\Enums\DietaryRequirement as Diet;
use App\Enums\MenuCourse;
use App\Models\Category;
use App\Models\DietaryTag;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The MORI Thai menu: one venue, three courses, and the dietary
 * classifications that apply to each dish.
 *
 * Dietary tags describe what is in the recipe, not a guarantee about the
 * kitchen. Every tag is decided from the full ingredient list — sauces,
 * marinades, frying oil, toppings and garnishes included — so a dish is never
 * classified from its name alone. Allergens are recorded separately and list
 * only the groups the recipe actually contains; a short list never means the
 * dish is safe for someone with that allergy.
 *
 * Idempotent: the venue, categories and dishes are keyed on their slugs, so
 * running the seeder twice updates rather than duplicates.
 */
class ThaiMenuSeeder extends Seeder
{
    private const RESTAURANT_SLUG = 'mori-thai-kitchen';

    /**
     * @var array<int, array{course: MenuCourse, category: string, items: array<int, array{name: string, slug: string, price: float, description: string, ingredients: string, allergens: array<int, Allergen>, image: string, ext?: string, featured?: bool, diet: array<int, Diet>}>}>
     */
    private array $menu = [
        [
            'course' => MenuCourse::Starter,
            'category' => 'Starters',
            'items' => [
                [
                    'name' => 'Thai Spring Rolls',
                    'slug' => 'thai-spring-rolls',
                    'price' => 120.00,
                    'description' => 'Crisp wheat-wrapper rolls filled with minced pork, glass noodles, cabbage and carrot, fried in rice bran oil. Served with sweet chilli sauce.',
                    'ingredients' => 'Wheat spring-roll wrappers, minced pork, glass noodles, cabbage, carrot, rice bran oil, sweet chilli sauce.',
                    'allergens' => [Allergen::Wheat],
                    'image' => 'thai-spring-rolls',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Vegetable Spring Rolls',
                    'slug' => 'vegetable-spring-rolls',
                    'price' => 110.00,
                    'description' => 'Egg-free wheat wrappers packed with cabbage, carrot, shiitake and glass noodles, fried in rice bran oil. Sweet chilli dipping sauce.',
                    'ingredients' => 'Wheat spring-roll wrappers, cabbage, carrot, shiitake, glass noodles, rice bran oil, sweet chilli sauce.',
                    'allergens' => [Allergen::Wheat],
                    'image' => 'vegetable-spring-rolls',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Chicken Satay',
                    'slug' => 'chicken-satay',
                    'price' => 165.00,
                    'description' => 'Grilled chicken skewers marinated in turmeric, lemongrass and coconut milk. Served with peanut sauce, cucumber relish and toast points.',
                    'ingredients' => 'Chicken, turmeric, lemongrass, coconut milk, peanut sauce, cucumber relish, toast points.',
                    'allergens' => [Allergen::Peanuts, Allergen::Wheat],
                    'image' => 'chicken-satay',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Shrimp Cakes',
                    'slug' => 'shrimp-cakes',
                    'price' => 180.00,
                    'description' => 'Tod mun kung — minced prawns bound with red curry paste, egg and fish sauce, deep fried and served with sweet chilli and cucumber.',
                    'ingredients' => 'Prawns, red curry paste, egg, fish sauce, rice bran oil, sweet chilli sauce, cucumber.',
                    'allergens' => [Allergen::Shellfish, Allergen::Eggs, Allergen::Fish],
                    'image' => 'shrimp-cakes',
                    'diet' => [],
                ],
                [
                    'name' => 'Thai Papaya Salad',
                    'slug' => 'papaya-salad',
                    'price' => 120.00,
                    'description' => 'Som tam — shredded green papaya with long beans, tomato and dried shrimp in a lime, fish sauce and palm sugar dressing, finished with roasted peanuts.',
                    'ingredients' => 'Green papaya, long beans, tomato, dried shrimp, lime, fish sauce, palm sugar, roasted peanuts.',
                    'allergens' => [Allergen::Shellfish, Allergen::Fish, Allergen::Peanuts],
                    'image' => 'papaya-salad',
                    'diet' => [],
                ],
                [
                    'name' => 'Edamame',
                    'slug' => 'edamame',
                    'price' => 90.00,
                    'description' => 'Steamed soy beans tossed with flaked sea salt and a little sesame oil.',
                    'ingredients' => 'Soy beans, sea salt, sesame oil.',
                    'allergens' => [Allergen::Soy, Allergen::Sesame],
                    'image' => 'edamame',
                    'ext' => 'png',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Tofu Salad',
                    'slug' => 'tofu-salad',
                    'price' => 140.00,
                    'description' => 'Crisp fried tofu with shallots, mint, coriander and chilli, dressed with lime, soy sauce and a little palm sugar. Sesame seeds on top.',
                    'ingredients' => 'Tofu, shallots, mint, coriander, chilli, lime, soy sauce, palm sugar, sesame seeds.',
                    'allergens' => [Allergen::Soy, Allergen::Sesame, Allergen::Wheat],
                    'image' => 'tofu-salad',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Vegetable Dumplings',
                    'slug' => 'vegetable-dumplings',
                    'price' => 130.00,
                    'description' => 'Steamed egg-free wheat dumplings filled with cabbage, carrot and shiitake, served with a soy and black vinegar dip.',
                    'ingredients' => 'Wheat dumpling wrappers, cabbage, carrot, shiitake, soy sauce, black vinegar.',
                    'allergens' => [Allergen::Wheat, Allergen::Soy],
                    'image' => 'vegetable-dumplings',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
            ],
        ],
        [
            'course' => MenuCourse::Main,
            'category' => 'Mains',
            'items' => [
                [
                    'name' => 'Pad Thai',
                    'slug' => 'pad-thai',
                    'price' => 180.00,
                    'description' => 'Rice noodles wok-fried with egg, tofu, tamarind and fish sauce, topped with roasted peanuts, bean sprouts and lime.',
                    'ingredients' => 'Rice noodles, egg, tofu, tamarind, fish sauce, roasted peanuts, bean sprouts, lime.',
                    'allergens' => [Allergen::Eggs, Allergen::Soy, Allergen::Fish, Allergen::Peanuts],
                    'image' => 'pad-thai',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Pad See Ew',
                    'slug' => 'pad-see-ew',
                    'price' => 175.00,
                    'description' => 'Wide rice noodles wok-fried with egg, Chinese broccoli and chicken in sweet soy and oyster sauce.',
                    'ingredients' => 'Wide rice noodles, egg, Chinese broccoli, chicken, sweet soy sauce, oyster sauce.',
                    'allergens' => [Allergen::Eggs, Allergen::Soy, Allergen::Wheat, Allergen::Shellfish],
                    'image' => 'pad-see-ew',
                    'diet' => [],
                ],
                [
                    'name' => 'Thai Basil Chicken with Rice',
                    'slug' => 'basil-chicken',
                    'price' => 165.00,
                    'description' => 'Pad kra pao — minced chicken stir-fried with holy basil, garlic and birds-eye chilli in fish and oyster sauce, over jasmine rice with a fried egg.',
                    'ingredients' => 'Minced chicken, holy basil, garlic, birds-eye chilli, fish sauce, oyster sauce, jasmine rice, egg.',
                    'allergens' => [Allergen::Fish, Allergen::Shellfish, Allergen::Eggs],
                    'image' => 'basil-chicken',
                    'diet' => [],
                ],
                [
                    'name' => 'Green Curry',
                    'slug' => 'green-curry',
                    'price' => 195.00,
                    'description' => 'Chicken simmered in green curry paste and coconut milk with Thai aubergine, bamboo and sweet basil. Served with jasmine rice.',
                    'ingredients' => 'Chicken, green curry paste, coconut milk, Thai aubergine, bamboo, sweet basil, jasmine rice.',
                    'allergens' => [Allergen::Fish, Allergen::Shellfish],
                    'image' => 'green-curry',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Red Curry',
                    'slug' => 'red-curry',
                    'price' => 195.00,
                    'description' => 'Chicken and mixed vegetables in a red curry paste and coconut milk sauce with kaffir lime and basil. Served with jasmine rice.',
                    'ingredients' => 'Chicken, mixed vegetables, red curry paste, coconut milk, kaffir lime, basil, jasmine rice.',
                    'allergens' => [Allergen::Fish, Allergen::Shellfish],
                    'image' => 'red-curry',
                    'diet' => [],
                ],
                [
                    'name' => 'Massaman Curry',
                    'slug' => 'massaman-curry',
                    'price' => 240.00,
                    'description' => 'Slow-braised beef with potato, onion and roasted peanuts in a massaman coconut curry with tamarind and palm sugar. Served with jasmine rice.',
                    'ingredients' => 'Beef, potato, onion, roasted peanuts, massaman curry paste, coconut milk, tamarind, palm sugar, jasmine rice.',
                    'allergens' => [Allergen::Peanuts, Allergen::Fish, Allergen::Shellfish],
                    'image' => 'massaman-curry',
                    'diet' => [],
                ],
                [
                    'name' => 'Chicken Fried Rice',
                    'slug' => 'chicken-fried-rice',
                    'price' => 155.00,
                    'description' => 'Jasmine rice wok-fried with chicken, egg, peas and carrot in light soy sauce, with cucumber and spring onion.',
                    'ingredients' => 'Jasmine rice, chicken, egg, peas, carrot, light soy sauce, cucumber, spring onion.',
                    'allergens' => [Allergen::Eggs, Allergen::Soy, Allergen::Wheat],
                    'image' => 'chicken-fried-rice',
                    'diet' => [],
                ],
                [
                    'name' => 'Pineapple Fried Rice',
                    'slug' => 'pineapple-fried-rice',
                    'price' => 185.00,
                    'description' => 'Jasmine rice with prawns, egg, pineapple and cashew nuts, seasoned with curry powder and soy sauce. Served in the pineapple.',
                    'ingredients' => 'Jasmine rice, prawns, egg, pineapple, cashew nuts, curry powder, soy sauce.',
                    'allergens' => [Allergen::Shellfish, Allergen::Eggs, Allergen::TreeNuts, Allergen::Soy, Allergen::Wheat],
                    'image' => 'pineapple-fried-rice',
                    'diet' => [],
                ],
                [
                    'name' => 'Tom Yum Soup with Rice',
                    'slug' => 'tom-yum',
                    'price' => 210.00,
                    'description' => 'Hot and sour broth with prawns, lemongrass, galangal, kaffir lime and straw mushrooms, finished with lime juice and coriander. Served with jasmine rice.',
                    'ingredients' => 'Prawns, lemongrass, galangal, kaffir lime, straw mushrooms, lime juice, coriander, jasmine rice.',
                    'allergens' => [Allergen::Shellfish],
                    'image' => 'tom-yum',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Grilled Chicken with Rice',
                    'slug' => 'grilled-chicken',
                    'price' => 190.00,
                    'description' => 'A plain grilled chicken breast, seasoned with salt and pepper and finished with a little butter, served over jasmine rice with steamed vegetables.',
                    'ingredients' => 'Chicken breast, salt, pepper, butter, jasmine rice, steamed vegetables.',
                    'allergens' => [Allergen::Milk],
                    'image' => 'grilled-chicken',
                    'diet' => [],
                ],
                [
                    'name' => 'Fried Chicken',
                    'slug' => 'fried-chicken',
                    'price' => 175.00,
                    'description' => 'Buttermilk-marinated chicken thigh in a seasoned flour crust, fried until golden. Served with jasmine rice and a side of sweet chilli sauce.',
                    'ingredients' => 'Chicken thigh, buttermilk, seasoned wheat flour, frying oil, jasmine rice, sweet chilli sauce.',
                    'allergens' => [Allergen::Milk, Allergen::Wheat],
                    'image' => 'fried-chicken',
                    'featured' => true,
                    'diet' => [],
                ],
                [
                    'name' => 'Tofu Stir-Fry',
                    'slug' => 'tofu-stir-fry',
                    'price' => 150.00,
                    'description' => 'Crisp tofu tossed with broccoli, bell pepper and garlic in gluten-free soy sauce and sesame oil, over jasmine rice.',
                    'ingredients' => 'Tofu, broccoli, bell pepper, garlic, gluten-free soy sauce, sesame oil, jasmine rice.',
                    'allergens' => [Allergen::Soy, Allergen::Sesame],
                    'image' => 'tofu-stir-fry',
                    'ext' => 'png',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Vegetable Curry',
                    'slug' => 'vegetable-curry',
                    'price' => 165.00,
                    'description' => 'Mixed vegetables and tofu in a vegetarian red curry paste with coconut milk and Thai basil. Served with jasmine rice.',
                    'ingredients' => 'Mixed vegetables, tofu, vegetarian red curry paste, coconut milk, Thai basil, jasmine rice.',
                    'allergens' => [Allergen::Soy],
                    'image' => 'vegetable-curry',
                    'ext' => 'png',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Salmon Rice Bowl',
                    'slug' => 'salmon-rice-bowl',
                    'price' => 320.00,
                    'description' => 'Grilled salmon over jasmine rice with edamame, cucumber and pickled ginger, drizzled with gluten-free soy and sesame dressing.',
                    'ingredients' => 'Salmon, jasmine rice, edamame, cucumber, pickled ginger, gluten-free soy sauce, sesame dressing.',
                    'allergens' => [Allergen::Fish, Allergen::Soy, Allergen::Sesame],
                    'image' => 'salmon-rice-bowl',
                    'diet' => [],
                ],
                [
                    'name' => 'Chicken Teriyaki Rice Bowl',
                    'slug' => 'teriyaki-rice-bowl',
                    'price' => 210.00,
                    'description' => 'Grilled chicken glazed with teriyaki sauce over jasmine rice with steamed broccoli and spring onion.',
                    'ingredients' => 'Chicken, teriyaki sauce, jasmine rice, steamed broccoli, spring onion.',
                    'allergens' => [Allergen::Soy, Allergen::Wheat],
                    'image' => 'teriyaki-rice-bowl',
                    'diet' => [],
                ],
                [
                    'name' => 'Beef Stir-Fry',
                    'slug' => 'beef-stir-fry',
                    'price' => 230.00,
                    'description' => 'Sliced beef wok-fried with broccoli, onion and garlic in oyster and soy sauce, over jasmine rice.',
                    'ingredients' => 'Beef, broccoli, onion, garlic, oyster sauce, soy sauce, jasmine rice.',
                    'allergens' => [Allergen::Shellfish, Allergen::Soy, Allergen::Wheat],
                    'image' => 'beef-stir-fry',
                    'diet' => [],
                ],
                [
                    'name' => 'Cauliflower Rice Bowl',
                    'slug' => 'cauliflower-rice-bowl',
                    'price' => 265.00,
                    'description' => 'Charred chicken thigh over cauliflower rice with avocado, cucumber, herbs, lime and olive oil. No starchy sides, no added sugar.',
                    'ingredients' => 'Chicken thigh, cauliflower rice, avocado, cucumber, herbs, lime, olive oil.',
                    'allergens' => [],
                    'image' => 'cauliflower-rice-bowl',
                    'featured' => true,
                    'diet' => [Diet::Keto],
                ],
            ],
        ],
        [
            'course' => MenuCourse::Dessert,
            'category' => 'Desserts',
            'items' => [
                [
                    'name' => 'Mango Sticky Rice',
                    'slug' => 'mango-sticky-rice',
                    'price' => 145.00,
                    'description' => 'Warm glutinous rice with salted coconut cream and ripe Nam Dok Mai mango, finished with toasted sesame.',
                    'ingredients' => 'Glutinous rice, salted coconut cream, Nam Dok Mai mango, toasted sesame.',
                    'allergens' => [Allergen::Sesame],
                    'image' => 'mango-sticky-rice',
                    'featured' => true,
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Coconut Ice Cream',
                    'slug' => 'coconut-ice-cream',
                    'price' => 95.00,
                    'description' => 'Two scoops of coconut milk ice cream with roasted peanut crumble and palm sugar syrup.',
                    'ingredients' => 'Coconut milk, roasted peanut crumble, palm sugar syrup.',
                    'allergens' => [Allergen::Peanuts],
                    'image' => 'coconut-ice-cream',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Thai Banana in Coconut Milk',
                    'slug' => 'banana-coconut',
                    'price' => 85.00,
                    'description' => 'Kluay buat chi — banana simmered in lightly sweetened coconut milk with a pinch of salt, topped with sesame seeds.',
                    'ingredients' => 'Banana, coconut milk, salt, sesame seeds.',
                    'allergens' => [Allergen::Sesame],
                    'image' => 'banana-coconut',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
                [
                    'name' => 'Bingsu',
                    'slug' => 'bingsu',
                    'price' => 165.00,
                    'description' => 'Korean-style hojicha shaved milk ice piled high, topped with cream and jelly and served with boba pearls and condensed milk on the side. Built for sharing.',
                    'ingredients' => 'Hojicha milk ice, cream, jelly, boba pearls, condensed milk.',
                    'allergens' => [Allergen::Milk],
                    'image' => 'bingsu',
                    'featured' => true,
                    'diet' => [Diet::Vegetarian],
                ],
                [
                    'name' => 'Tiramisu',
                    'slug' => 'tiramisu',
                    'price' => 155.00,
                    'description' => 'Espresso-soaked sponge layered with mascarpone cream and a dusting of cocoa. Made in house each morning.',
                    'ingredients' => 'Wheat sponge, eggs, espresso, mascarpone cream, cocoa.',
                    'allergens' => [Allergen::Wheat, Allergen::Eggs, Allergen::Milk],
                    'image' => 'tiramisu',
                    'diet' => [Diet::Vegetarian],
                ],
                [
                    'name' => 'Fresh Fruit Platter',
                    'slug' => 'fruit-platter',
                    'price' => 180.00,
                    'description' => 'Seasonal Thai fruit — mango, pineapple, rose apple and dragon fruit, cut to order.',
                    'ingredients' => 'Seasonal Thai fruit: mango, pineapple, rose apple, dragon fruit.',
                    'allergens' => [],
                    'image' => 'fruit-platter',
                    'diet' => [Diet::Vegetarian, Diet::Vegan],
                ],
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $restaurant = $this->seedRestaurant();
            $tagIds = DietaryTag::query()->pluck('id', 'slug');

            foreach ($this->menu as $index => $course) {
                $category = Category::query()->updateOrCreate(
                    ['restaurant_id' => $restaurant->id, 'name' => $course['category']],
                    ['sort_order' => $index],
                );

                foreach ($course['items'] as $item) {
                    $menuItem = MenuItem::query()->updateOrCreate(
                        ['restaurant_id' => $restaurant->id, 'slug' => $item['slug']],
                        [
                            'category_id' => $category->id,
                            'name' => $item['name'],
                            'course' => $course['course'],
                            'description' => $item['description'],
                            'ingredients' => $item['ingredients'],
                            'allergens' => $item['allergens'],
                            'price' => $item['price'],
                            'image_url' => '/images/menu/'.$item['image'].'.'.($item['ext'] ?? 'jpg'),
                            'is_available' => true,
                            'is_featured' => $item['featured'] ?? false,
                        ],
                    );

                    $menuItem->dietaryTags()->sync(
                        collect($item['diet'])
                            ->map(fn (Diet $diet): int => (int) $tagIds[$diet->value])
                            ->all(),
                    );
                }
            }
        });
    }

    private function seedRestaurant(): Restaurant
    {
        return Restaurant::query()->updateOrCreate(
            ['slug' => self::RESTAURANT_SLUG],
            [
                'name' => 'MORI Thai Kitchen',
                'phone' => '+66 2 123 4567',
                'address' => '88 Sukhumvit Soi 23, Khlong Toei Nuea, Watthana, Bangkok 10110',
                'opening_hours' => collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
                    ->mapWithKeys(fn (string $day): array => [$day => ['open' => '11:00', 'close' => '22:00']])
                    ->all(),
                'slot_minutes' => 90,
                'auto_confirm_reservations' => true,
                // Thai VAT and a Bangkok delivery fee, in THB.
                'delivery_fee' => 40.00,
                'min_delivery_amount' => 250.00,
                'tax_rate' => 0.0700,
                'is_active' => true,
            ],
        );
    }
}
