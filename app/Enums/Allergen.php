<?php

namespace App\Enums;

/**
 * The allergen groups tracked on a dish. A dish lists only the groups its
 * recipe actually contains; an empty list means none of these groups appear in
 * the recorded recipe, not that the dish is safe for a person with an allergy.
 * Kitchens share equipment and prep surfaces, so cross-contact is always
 * possible.
 */
enum Allergen: string
{
    case Peanuts = 'peanuts';
    case TreeNuts = 'tree-nuts';
    case Shellfish = 'shellfish';
    case Fish = 'fish';
    case Eggs = 'eggs';
    case Milk = 'milk';
    case Soy = 'soy';
    case Wheat = 'wheat';
    case Sesame = 'sesame';

    /**
     * The allergen group as it is shown to a customer.
     */
    public function label(): string
    {
        return match ($this) {
            self::Peanuts => 'Peanuts',
            self::TreeNuts => 'Tree nuts',
            self::Shellfish => 'Shellfish',
            self::Fish => 'Fish',
            self::Eggs => 'Eggs',
            self::Milk => 'Milk',
            self::Soy => 'Soy',
            self::Wheat => 'Wheat',
            self::Sesame => 'Sesame',
        };
    }
}
