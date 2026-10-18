<?php

namespace App\Models;

use App\Enums\FoodRestriction;
use App\Enums\FoodRestrictionKind;
use Database\Factories\CustomerFoodRestrictionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A food allergy, dietary restriction or dietary preference saved by a
 * customer. It belongs to exactly one user and is only read for that user.
 *
 * @property int $id
 * @property int $user_id
 * @property FoodRestriction $restriction
 * @property FoodRestrictionKind $kind
 * @property string|null $custom_label
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'restriction', 'kind', 'custom_label'])]
class CustomerFoodRestriction extends Model
{
    /** @use HasFactory<CustomerFoodRestrictionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'restriction' => FoodRestriction::class,
            'kind' => FoodRestrictionKind::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The wording shown to the customer: their own text for "Other" entries,
     * otherwise the fixed vocabulary label.
     */
    public function label(): string
    {
        if ($this->restriction->requiresCustomLabel()) {
            return filled($this->custom_label)
                ? (string) $this->custom_label
                : $this->restriction->label();
        }

        return $this->restriction->label();
    }
}
