<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'role' => $this->role->value,
            'no_show_count' => $this->no_show_count,
            'memberships' => $this->whenLoaded('memberships', fn () => $this->memberships->map(fn ($member): array => [
                'restaurant_id' => $member->restaurant_id,
                'restaurant_name' => $member->relationLoaded('restaurant') ? $member->restaurant?->name : null,
                'role' => $member->role->value,
                'staff_type' => $member->staff_type?->value,
            ])->all()),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
