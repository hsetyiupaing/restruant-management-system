<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('restaurants', 'slug')],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*' => ['nullable', 'array'],
            'opening_hours.*.open' => ['required_with:opening_hours.*', 'date_format:H:i'],
            'opening_hours.*.close' => ['required_with:opening_hours.*', 'date_format:H:i'],
            'slot_minutes' => ['sometimes', 'integer', 'min:15', 'max:480'],
            'auto_confirm_reservations' => ['sometimes', 'boolean'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'min_delivery_amount' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
