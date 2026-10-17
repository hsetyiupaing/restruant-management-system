<?php

namespace App\Http\Resources;

use App\Models\DeliveryDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeliveryDetail
 */
class DeliveryDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'driver' => $this->whenLoaded('driver', fn (): ?array => $this->driver === null ? null : [
                'id' => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'driver_id' => $this->driver_id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'address_line' => $this->address_line,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'delivery_status' => $this->delivery_status->value,
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'picked_up_at' => $this->picked_up_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'failure_reason' => $this->failure_reason,
        ];
    }
}
