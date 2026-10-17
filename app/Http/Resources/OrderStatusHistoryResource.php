<?php

namespace App\Http\Resources;

use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderStatusHistory
 */
class OrderStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'changed_by' => $this->whenLoaded('changedBy', fn (): ?array => $this->changedBy === null ? null : [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'changed_at' => $this->changed_at?->toIso8601String(),
            'reason' => $this->reason,
        ];
    }
}
