<?php

namespace App\Http\Resources;

use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'party_size' => $this->party_size,
            'start_time' => $this->start_time->toIso8601String(),
            'end_time' => $this->end_time->toIso8601String(),
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'special_request' => $this->special_request,
            'restaurant' => $this->whenLoaded('restaurant', fn (): array => [
                'id' => $this->restaurant->id,
                'name' => $this->restaurant->name,
                'slug' => $this->restaurant->slug,
            ]),
            'table' => $this->whenLoaded('table', fn (): array => [
                'id' => $this->table->id,
                'label' => $this->table->label,
                'capacity' => $this->table->capacity,
                'shape' => $this->table->shape->value,
                'pos_x' => $this->table->pos_x,
                'pos_y' => $this->table->pos_y,
            ]),
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
                'no_show_count' => $this->customer->no_show_count,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
