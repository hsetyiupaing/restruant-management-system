<?php

namespace App\Http\Resources;

use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Restaurant
 */
class RestaurantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'phone' => $this->phone,
            'address' => $this->address,
            'opening_hours' => $this->opening_hours,
            'is_active' => $this->is_active,
            'settings' => [
                'slot_minutes' => $this->slot_minutes,
                'auto_confirm_reservations' => $this->auto_confirm_reservations,
                'delivery_fee' => (string) $this->delivery_fee,
                'min_delivery_amount' => (string) $this->min_delivery_amount,
                'tax_rate' => (string) $this->tax_rate,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
