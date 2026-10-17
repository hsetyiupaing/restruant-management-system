<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonInterface;
use Database\Factories\ReservationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property int $customer_id
 * @property int $table_id
 * @property int $party_size
 * @property CarbonInterface $start_time
 * @property CarbonInterface $end_time
 * @property ReservationStatus $status
 * @property string|null $special_request
 * @property CarbonInterface|null $hold_expires_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'restaurant_id', 'customer_id', 'table_id', 'party_size', 'start_time',
    'end_time', 'status', 'special_request', 'hold_expires_at',
])]
class Reservation extends Model
{
    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'party_size' => 'integer',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'status' => ReservationStatus::class,
            'hold_expires_at' => 'datetime',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<DiningTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'table_id');
    }

    /**
     * @return HasMany<ReservationStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ReservationStatusHistory::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
