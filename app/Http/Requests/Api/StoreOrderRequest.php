<?php

namespace App\Http\Requests\Api;

use App\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Prices and totals are intentionally absent: they are recomputed server-side.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'exists:restaurants,id'],
            'order_type' => ['required', Rule::enum(OrderType::class)],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.menu_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
            'items.*.customizations' => ['sometimes', 'array', 'max:20'],
            'items.*.customizations.*' => ['array:group_id,option_ids'],
            'items.*.customizations.*.group_id' => ['required', 'string', 'max:80'],
            'items.*.customizations.*.option_ids' => ['required', 'array', 'max:30'],
            'items.*.customizations.*.option_ids.*' => ['required', 'string', 'max:80'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'reservation_id' => ['nullable', 'integer', 'exists:reservations,id'],
            'delivery' => ['required_if:order_type,DELIVERY', 'nullable', 'array'],
            'delivery.recipient_name' => ['required_if:order_type,DELIVERY', 'string', 'max:255'],
            'delivery.recipient_phone' => ['required_if:order_type,DELIVERY', 'string', 'max:32'],
            'delivery.address_line' => ['required_if:order_type,DELIVERY', 'string', 'max:255'],
            'delivery.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
