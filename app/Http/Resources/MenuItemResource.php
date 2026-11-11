<?php

namespace App\Http\Resources;

use App\Enums\Allergen;
use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'restaurant_id' => $this->restaurant_id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'name' => $this->name,
            'slug' => $this->slug,
            'course' => $this->course?->value,
            'description' => $this->description,
            'ingredients' => $this->ingredients,
            // Null means the kitchen has not recorded allergens; an empty list
            // means none of the tracked groups appear in the recorded recipe.
            'allergens' => $this->allergens?->map(fn (Allergen $allergen): array => [
                'value' => $allergen->value,
                'label' => $allergen->label(),
            ])->values()->all(),
            'price' => (string) $this->price,
            'image_url' => $this->imageUrl(),
            'is_available' => $this->is_available,
            'is_featured' => $this->is_featured,
            'customization_groups' => $this->customization_groups ?? [],
            'dietary_tags' => DietaryTagResource::collection($this->whenLoaded('dietaryTags')),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }

    private function imageUrl(): ?string
    {
        if ($this->image_url === null) {
            return null;
        }

        $menuImageDirectory = "restaurants/{$this->restaurant_id}/menu/";

        if (str_starts_with($this->image_url, $menuImageDirectory)) {
            $disk = app()->environment('production') ? 'cloud_public' : 'public';

            return Storage::disk($disk)->url($this->image_url);
        }

        return $this->image_url;
    }
}
