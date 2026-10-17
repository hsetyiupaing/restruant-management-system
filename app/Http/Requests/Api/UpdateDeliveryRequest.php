<?php

namespace App\Http\Requests\Api;

use App\Enums\DeliveryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'delivery_status' => ['sometimes', Rule::enum(DeliveryStatus::class)],
            'failure_reason' => ['required_if:delivery_status,FAILED', 'nullable', 'string', 'max:255'],
        ];
    }
}
