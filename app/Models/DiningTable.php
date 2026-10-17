<?php

namespace App\Models;

use App\Enums\TableShape;
use Database\Factories\DiningTableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property string $label
 * @property int $capacity
 * @property int $min_capacity
 * @property int $pos_x
 * @property int $pos_y
 * @property TableShape $shape
 * @property bool $is_active
 */
#[Fillable([
    'restaurant_id', 'label', 'capacity', 'min_capacity',
    'pos_x', 'pos_y', 'shape', 'is_active',
])]
class DiningTable extends Model
{
    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'min_capacity' => 'integer',
            'pos_x' => 'integer',
            'pos_y' => 'integer',
            'shape' => TableShape::class,
            'is_active' => 'boolean',
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
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'table_id');
    }

    public function canHost(int $partySize): bool
    {
        return $partySize >= $this->min_capacity && $partySize <= $this->capacity;
    }
}
