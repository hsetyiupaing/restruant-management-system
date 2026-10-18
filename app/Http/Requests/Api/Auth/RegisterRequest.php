<?php

namespace App\Http\Requests\Api\Auth;

use App\Concerns\FoodRestrictionRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RegisterRequest extends FormRequest
{
    use FoodRestrictionRules, PasswordValidationRules, ProfileValidationRules;

    /**
     * `role` is deliberately absent from the validated set: a supplied role is
     * ignored rather than accepted, and registration always creates a
     * CUSTOMER. Never add it here without also removing it from the payload the
     * controller persists.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
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
