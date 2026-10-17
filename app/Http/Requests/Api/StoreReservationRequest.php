<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'table_id' => ['required', 'integer', 'exists:dining_tables,id'],
            'party_size' => ['required', 'integer', 'min:1', 'max:100'],
            'start_time' => ['required', 'date', 'after:now'],
            'special_request' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
