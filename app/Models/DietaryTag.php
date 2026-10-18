<?php

namespace App\Models;

use App\Enums\DietaryRequirement;
use Database\Factories\DietaryTagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 */
#[Fillable(['name', 'slug', 'description'])]
class DietaryTag extends Model
{
    /** @use HasFactory<DietaryTagFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<MenuItem, $this>
     */
    public function menuItems(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class);
    }

    public function requirement(): ?DietaryRequirement
    {
        return DietaryRequirement::tryFrom($this->slug);
    }
}
