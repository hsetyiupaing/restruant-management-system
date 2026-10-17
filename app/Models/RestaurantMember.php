<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\StaffType;
use Database\Factories\RestaurantMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property int $user_id
 * @property MembershipRole $role
 * @property StaffType|null $staff_type
 */
#[Fillable(['restaurant_id', 'user_id', 'role', 'staff_type'])]
class RestaurantMember extends Model
{
    /** @use HasFactory<RestaurantMemberFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'staff_type' => StaffType::class,
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
