<?php

namespace App\Http\Resources;

use App\Models\RestaurantMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RestaurantMember
 */
class RestaurantMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'user_id' => $this->user_id,
            'role' => $this->role->value,
            'staff_type' => $this->staff_type?->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
