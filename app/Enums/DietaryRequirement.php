<?php

namespace App\Enums;

/**
 * The dietary classifications the storefront filters by. The values double as
 * the `dietary_tags.slug` of each tag, so they are the stable identifiers the
 * API and the frontend exchange.
 *
 * These describe an ingredient profile, not a safety guarantee: a kitchen
 * shares pans and prep surfaces, so nothing here claims an item is free from
 * allergen contact. Allergens are tracked separately on the dish itself and
 * customer restrictions are reviewed at checkout.
 */
enum DietaryRequirement: string
{
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Keto = 'keto';

    public function label(): string
    {
        return match ($this) {
            self::Vegetarian => 'Vegetarian',
            self::Vegan => 'Vegan',
            self::Keto => 'Keto',
        };
    }

    /**
     * A short explanation of what the classification means on this menu.
     */
    public function description(): string
    {
        return match ($this) {
            self::Vegetarian => 'No meat, poultry or fish in the recipe.',
            self::Vegan => 'No animal products, including egg, dairy, fish sauce and oyster sauce.',
            self::Keto => 'Low-carbohydrate, without rice, noodles, sugar-sweetened sauces or starchy vegetables.',
        };
    }
}
