<?php

namespace App\Http\Requests\Api\Auth;

use App\Concerns\FoodRestrictionRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edits the signed-in customer's own restriction list. No user id is accepted
 * from the request, so this endpoint can only ever write its caller's rows.
 */
class UpdateFoodRestrictionsRequest extends FormRequest
{
    use FoodRestrictionRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->foodRestrictionRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateFoodRestrictions(
                $validator,
                $this->input('food_restrictions', []),
            ),
        ];
    }
}
