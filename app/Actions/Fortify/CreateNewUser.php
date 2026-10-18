<?php

namespace App\Actions\Fortify;

use App\Concerns\FoodRestrictionRules;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\FoodRestrictionService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Creates an account from the web registration form. Like the API endpoint,
 * this always creates a CUSTOMER: a role posted with the form is never read.
 */
class CreateNewUser implements CreatesNewUsers
{
    use FoodRestrictionRules, PasswordValidationRules, ProfileValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $validator = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            ...$this->foodRestrictionRules(),
        ]);

        $this->validateFoodRestrictions($validator, $input['food_restrictions'] ?? []);

        $validator->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'password' => $input['password'],
            'role' => UserRole::Customer,
        ]);

        app(FoodRestrictionService::class)->replaceFor(
            $user,
            $this->foodRestrictionRows($input['food_restrictions'] ?? []),
        );

        return $user;
    }
}
