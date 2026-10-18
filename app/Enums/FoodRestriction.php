<?php

namespace App\Enums;

/**
 * The stable identifiers a customer can save about their food needs.
 *
 * The value is what is stored, so labels may change without rewriting rows.
 * Allergies map onto the allergen groups recorded on a dish; gluten- and
 * lactose-related restrictions map onto wheat and milk. Preferences map onto
 * the menu's dietary classifications. "Other" entries carry a customer-supplied
 * label and are never silently matched against a dish, because the kitchen
 * cannot verify them.
 */
enum FoodRestriction: string
{
    // ---------- Allergies ----------
    case Peanuts = 'peanuts';
    case TreeNuts = 'tree-nuts';
    case Shellfish = 'shellfish';
    case Crustaceans = 'crustaceans';
    case Fish = 'fish';
    case Eggs = 'eggs';
    case Milk = 'milk';
    case Soy = 'soy';
    case Wheat = 'wheat';
    case Sesame = 'sesame';
    case OtherAllergy = 'other-allergy';

    // ---------- Dietary and medical restrictions ----------
    case GlutenFreeRequirement = 'gluten-free-requirement';
    case CeliacDisease = 'celiac-disease';
    case GlutenSensitivity = 'gluten-sensitivity';
    case LactoseIntolerance = 'lactose-intolerance';
    case OtherIntolerance = 'other-intolerance';
    case OtherDietaryRestriction = 'other-dietary-restriction';

    // ---------- Dietary preferences ----------
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Keto = 'keto';

    public function kind(): FoodRestrictionKind
    {
        return match ($this) {
            self::Peanuts,
            self::TreeNuts,
            self::Shellfish,
            self::Crustaceans,
            self::Fish,
            self::Eggs,
            self::Milk,
            self::Soy,
            self::Wheat,
            self::Sesame,
            self::OtherAllergy => FoodRestrictionKind::Allergy,

            self::GlutenFreeRequirement,
            self::CeliacDisease,
            self::GlutenSensitivity,
            self::LactoseIntolerance,
            self::OtherIntolerance,
            self::OtherDietaryRestriction => FoodRestrictionKind::DietaryRestriction,

            self::Vegetarian,
            self::Vegan,
            self::Keto => FoodRestrictionKind::DietaryPreference,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Peanuts => 'Peanuts',
            self::TreeNuts => 'Tree nuts',
            self::Shellfish => 'Shellfish (molluscs)',
            self::Crustaceans => 'Crustaceans (prawns, crab, lobster)',
            self::Fish => 'Fish',
            self::Eggs => 'Eggs',
            self::Milk => 'Milk',
            self::Soy => 'Soy',
            self::Wheat => 'Wheat',
            self::Sesame => 'Sesame',
            self::OtherAllergy => 'Other allergy',
            self::GlutenFreeRequirement => 'Gluten-free requirement',
            self::CeliacDisease => 'Celiac disease',
            self::GlutenSensitivity => 'Gluten sensitivity',
            self::LactoseIntolerance => 'Lactose intolerance',
            self::OtherIntolerance => 'Other food intolerance',
            self::OtherDietaryRestriction => 'Other dietary restriction',
            self::Vegetarian => 'Vegetarian',
            self::Vegan => 'Vegan',
            self::Keto => 'Keto',
        };
    }

    /**
     * "Other" entries are meaningless without the customer's own wording.
     */
    public function requiresCustomLabel(): bool
    {
        return in_array($this, [
            self::OtherAllergy,
            self::OtherIntolerance,
            self::OtherDietaryRestriction,
        ], true);
    }

    /**
     * The allergen group this restriction should be checked against on a dish,
     * or null when the kitchen cannot verify it.
     *
     * Crustaceans are recorded under the shared shellfish group, so they match
     * that group rather than a separate crustacean flag.
     */
    public function allergen(): ?Allergen
    {
        return match ($this) {
            self::Peanuts => Allergen::Peanuts,
            self::TreeNuts => Allergen::TreeNuts,
            self::Shellfish, self::Crustaceans => Allergen::Shellfish,
            self::Fish => Allergen::Fish,
            self::Eggs => Allergen::Eggs,
            self::Milk, self::LactoseIntolerance => Allergen::Milk,
            self::Soy => Allergen::Soy,
            self::Wheat,
            self::GlutenFreeRequirement,
            self::CeliacDisease,
            self::GlutenSensitivity => Allergen::Wheat,
            self::Sesame => Allergen::Sesame,
            default => null,
        };
    }

    /**
     * The menu classification this preference should be checked against.
     */
    public function dietaryTag(): ?string
    {
        return match ($this) {
            self::Vegetarian => DietaryRequirement::Vegetarian->value,
            self::Vegan => DietaryRequirement::Vegan->value,
            self::Keto => DietaryRequirement::Keto->value,
            default => null,
        };
    }

    /**
     * The vocabulary the registration and account forms render, grouped the
     * same way a customer thinks about it.
     *
     * @return array<string, array<int, array{value: string, label: string, requires_custom_label: bool}>>
     */
    public static function catalog(): array
    {
        $groups = [
            'allergies' => [],
            'dietary_restrictions' => [],
            'dietary_preferences' => [],
        ];

        foreach (self::cases() as $case) {
            $group = match ($case->kind()) {
                FoodRestrictionKind::Allergy => 'allergies',
                FoodRestrictionKind::DietaryRestriction => 'dietary_restrictions',
                FoodRestrictionKind::DietaryPreference => 'dietary_preferences',
            };

            $groups[$group][] = [
                'value' => $case->value,
                'label' => $case->label(),
                'requires_custom_label' => $case->requiresCustomLabel(),
            ];
        }

        return $groups;
    }
}
