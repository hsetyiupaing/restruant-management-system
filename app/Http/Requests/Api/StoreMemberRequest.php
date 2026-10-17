<?php

namespace App\Http\Requests\Api;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('restaurant_members', 'user_id')->where('restaurant_id', $restaurant->id),
            ],
            'role' => ['required', Rule::enum(MembershipRole::class)],
            'staff_type' => ['nullable', Rule::enum(StaffType::class)],
        ];
    }
}
