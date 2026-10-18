<?php

namespace App\Models;

use App\Enums\Allergen;
use App\Enums\MenuCourse;
use Carbon\CarbonInterface;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $restaurant_id
 * @property int $category_id
 * @property string $name
 * @property string|null $slug
 * @property MenuCourse|null $course
 * @property string|null $description
 * @property string|null $ingredients
 * @property array<int, Allergen>|null $allergens
 * @property string $price
 * @property string|null $image_url
 * @property bool $is_available
 * @property bool $is_featured
 * @property array<int, array{id: string, name: string, type: string, options: array<int, array{id: string, name: string, price: string|int|float}>}>|null $customization_groups
 * @property CarbonInterface|null $deleted_at
 */
#[Fillable([
    'restaurant_id', 'category_id', 'name', 'slug', 'course', 'description',
    'ingredients', 'allergens', 'price', 'image_url', 'is_available',
    'is_featured', 'customization_groups',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'course' => MenuCourse::class,
            'allergens' => AsEnumCollection::class.':'.Allergen::class,
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'customization_groups' => 'array',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<DietaryTag, $this>
     */
    public function dietaryTags(): BelongsToMany
    {
        return $this->belongsToMany(DietaryTag::class);
    }

    /**
     * Give every dish a URL-safe slug, unique inside its restaurant.
     */
    protected static function booted(): void
    {
        static::creating(function (MenuItem $item): void {
            if (blank($item->slug)) {
                $item->slug = $item->uniqueSlug($item->name);
            }
        });

        static::updating(function (MenuItem $item): void {
            if ($item->isDirty('name') && ! $item->isDirty('slug')) {
                $item->slug = $item->uniqueSlug($item->name);
            }
        });
    }

    /**
     * Admin routes keep numeric IDs; the public catalogue addresses dishes by
     * slug. Resolve either so existing clients are not broken.
     *
     * @param  \Illuminate\Contracts\Database\Eloquent\Builder<MenuItem>|null  $query
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        return $query->where(function (Builder $inner) use ($value): void {
            $inner->where($this->getRouteKeyName(), $value)
                ->orWhere('slug', $value);
        });
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'dish';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()
            ->where('restaurant_id', $this->restaurant_id)
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Restrict a query to what the public menu may show.
     *
     * @param  Builder<MenuItem>  $query
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true);
    }

    /**
     * @param  Builder<MenuItem>  $query
     */
    public function scopeOfCourse(Builder $query, MenuCourse $course): void
    {
        $query->where('course', $course->value);
    }

    /**
     * Require every requested dietary classification, not just one of them:
     * someone filtering for vegan and gluten free needs both to hold.
     *
     * @param  Builder<MenuItem>  $query
     * @param  array<int, string>  $slugs
     */
    public function scopeWithAllDietaryTags(Builder $query, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $query->whereHas('dietaryTags', fn (Builder $inner) => $inner->where('slug', $slug));
        }
    }
}
