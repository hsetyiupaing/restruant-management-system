<?php

namespace App\Models;

use App\Enums\MembershipRole;
use Carbon\CarbonInterface;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $phone
 * @property string|null $address
 * @property array<string, mixed>|null $opening_hours
 * @property int $slot_minutes
 * @property bool $auto_confirm_reservations
 * @property string $delivery_fee
 * @property string $min_delivery_amount
 * @property string $tax_rate
 * @property bool $is_active
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'name', 'slug', 'phone', 'address', 'opening_hours', 'slot_minutes',
    'auto_confirm_reservations', 'delivery_fee', 'min_delivery_amount',
    'tax_rate', 'is_active',
])]
class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'slot_minutes' => 'integer',
            'auto_confirm_reservations' => 'boolean',
            'delivery_fee' => 'decimal:2',
            'min_delivery_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RestaurantMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(RestaurantMember::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * @return HasMany<DiningTable, $this>
     */
    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function hasMember(User $user, MembershipRole $minimumRole = MembershipRole::Staff): bool
    {
        $member = $this->members()->where('user_id', $user->id)->first();

        if ($member === null) {
            return false;
        }

        return $minimumRole === MembershipRole::Staff || $member->role === MembershipRole::Admin;
    }
}
