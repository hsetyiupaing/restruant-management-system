<?php

namespace App\Http\Requests\Api\Auth;

use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Users may only edit their own display identity. Role, no-show count
        // and membership are never client-writable through this endpoint.
        return $this->profileRules($this->user()?->id);
    }
}
