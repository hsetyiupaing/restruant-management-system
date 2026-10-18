<?php

namespace App\Enums;

/**
 * How a saved customer restriction is used. Allergies and medical dietary
 * restrictions are needs, not preferences; keeping the kind separate stops the
 * UI from presenting them as lifestyle choices or filtering the menu by them.
 */
enum FoodRestrictionKind: string
{
    case Allergy = 'ALLERGY';
    case DietaryRestriction = 'DIETARY_RESTRICTION';
    case DietaryPreference = 'DIETARY_PREFERENCE';

    public function label(): string
    {
        return match ($this) {
            self::Allergy => 'Food allergy',
            self::DietaryRestriction => 'Dietary restriction',
            self::DietaryPreference => 'Dietary preference',
        };
    }
}
