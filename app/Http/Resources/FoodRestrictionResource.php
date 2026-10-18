<?php

namespace App\Http\Resources;

use App\Enums\FoodRestriction;
use App\Models\CustomerFoodRestriction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerFoodRestriction
 */
class FoodRestrictionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'restriction' => $this->restriction->value,
            'kind' => $this->kind->value,
            'label' => $this->label(),
            'custom_label' => $this->custom_label,
            'requires_custom_label' => $this->restriction->requiresCustomLabel(),
            'allergen' => $this->restriction->allergen()?->value,
            'dietary_tag' => $this->restriction->dietaryTag(),
        ];
    }

    /**
     * The selectable vocabulary, with labels resolved server-side so the
     * client never invents a restriction the backend would reject.
     *
     * @return array<string, array<int, array{value: string, label: string, requires_custom_label: bool}>>
     */
    public static function catalog(): array
    {
        return FoodRestriction::catalog();
    }
}
