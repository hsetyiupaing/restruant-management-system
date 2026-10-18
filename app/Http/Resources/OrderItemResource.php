<?php

namespace App\Http\Resources;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_item_id' => $this->menu_item_id,
            'name' => $this->item_name_snapshot,
            'unit_price' => (string) $this->unit_price,
            'quantity' => $this->quantity,
            'line_total' => (string) $this->line_total,
            'note' => $this->note,
            'selected_customizations' => $this->selected_customizations ?? [],
        ];
    }
}
