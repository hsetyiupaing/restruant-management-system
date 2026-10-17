<?php

namespace App\Http\Resources;

use App\Models\DiningTable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Includes floor-plan coordinates so the Vue map can render directly.
 *
 * @mixin DiningTable
 */
class DiningTableResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'capacity' => $this->capacity,
            'min_capacity' => $this->min_capacity,
            'shape' => $this->shape->value,
            'pos_x' => $this->pos_x,
            'pos_y' => $this->pos_y,
            'is_active' => $this->is_active,
        ];
    }
}
