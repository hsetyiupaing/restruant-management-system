<?php

namespace App\Http\Requests\Api;

use App\Enums\StaffType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];
        $role = $this->input('role');
        $staffType = $this->input('staff_type');

        if (is_string($role)) {
            $normalized['role'] = strtoupper($role);
        }

        if (is_string($staffType)) {
            $normalized['staff_type'] = strtoupper($staffType);
        }

        $this->merge($normalized);
    }

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            && ($this->user()?->can('changeRole', $target) ?? false);
    }

    public function hasStaffType(): bool
    {
        return array_key_exists('staff_type', $this->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => [
                'required',
                Rule::enum(UserRole::class)->only([
                    UserRole::Customer,
                    UserRole::Staff,
                    UserRole::Admin,
                ]),
            ],
            'staff_type' => [
                Rule::when(
                    $this->input('role') === UserRole::Staff->value,
                    ['sometimes', 'nullable', Rule::enum(StaffType::class)],
                    ['prohibited'],
                ),
            ],
        ];
    }
}
