<?php

namespace App\Http\Requests\Api\Auth;

use App\Concerns\FoodRestrictionRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    use FoodRestrictionRules, ProfileValidationRules;

    /**
     * Users may only edit their own display identity and food restrictions.
     * Role, no-show count and membership are never client-writable through this
     * endpoint.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->user()?->id),
            ...$this->foodRestrictionRules(),
        ];
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
