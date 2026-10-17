<?php

namespace App\Http\Requests\Api;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::enum(MembershipRole::class)],
            'staff_type' => ['sometimes', 'nullable', Rule::enum(StaffType::class)],
        ];
    }
}
