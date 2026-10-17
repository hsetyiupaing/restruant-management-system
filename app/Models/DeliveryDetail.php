<?php

namespace App\Models;

use App\Enums\DeliveryStatus;
use Carbon\CarbonInterface;
use Database\Factories\DeliveryDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $driver_id
 * @property string $recipient_name
 * @property string $recipient_phone
 * @property string $address_line
 * @property string|null $latitude
 * @property string|null $longitude
 * @property DeliveryStatus $delivery_status
 * @property CarbonInterface|null $assigned_at
 * @property CarbonInterface|null $picked_up_at
 * @property CarbonInterface|null $delivered_at
 * @property string|null $failure_reason
 */
#[Fillable([
    'order_id', 'driver_id', 'recipient_name', 'recipient_phone',
    'address_line', 'latitude', 'longitude', 'delivery_status',
    'assigned_at', 'picked_up_at', 'delivered_at', 'failure_reason',
])]
class DeliveryDetail extends Model
{
    /** @use HasFactory<DeliveryDetailFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'delivery_status' => DeliveryStatus::class,
            'assigned_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
