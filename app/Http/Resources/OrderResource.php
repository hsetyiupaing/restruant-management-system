<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type->value,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'restaurant' => $this->whenLoaded('restaurant', fn (): array => [
                'id' => $this->restaurant->id,
                'name' => $this->restaurant->name,
                'slug' => $this->restaurant->slug,
            ]),
            'customer' => $this->whenLoaded('customer', fn (): ?array => $this->customer === null ? null : [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'email' => $this->customer->email,
            ]),
            'reservation' => $this->whenLoaded('reservation', fn (): ?array => $this->reservation === null ? null : [
                'id' => $this->reservation->id,
                'status' => $this->reservation->status->value,
                'start_time' => $this->reservation->start_time->toIso8601String(),
                'end_time' => $this->reservation->end_time->toIso8601String(),
                'party_size' => $this->reservation->party_size,
            ]),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'delivery' => $this->whenLoaded('deliveryDetail', fn () => $this->deliveryDetail === null
                ? null
                : new DeliveryDetailResource($this->deliveryDetail)),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'status_history' => OrderStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'totals' => [
                'subtotal' => (string) $this->subtotal,
                'delivery_fee' => (string) $this->delivery_fee,
                'tax' => (string) $this->tax,
                'discount' => (string) $this->discount,
                'total' => (string) $this->total,
            ],
            'scheduled_for' => $this->scheduled_for?->toIso8601String(),
            'customer_note' => $this->customer_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
