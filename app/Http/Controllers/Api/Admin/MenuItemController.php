<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMenuItemImageRequest;
use App\Http\Requests\Api\StoreMenuItemRequest;
use App\Http\Requests\Api\UpdateMenuItemAvailabilityRequest;
use App\Http\Requests\Api\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\DietaryTag;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class MenuItemController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('manageMenu', $restaurant);

        // Soft-deleted dishes are kept only so historical order snapshots stay
        // resolvable; they are no longer part of the menu, so the admin list
        // must not show them as live rows. Pass `trashed=1` to audit them.
        $items = $restaurant->menuItems()
            ->with(['category', 'dietaryTags'])
            ->when(! $request->boolean('trashed'), fn ($query) => $query->whereNull('deleted_at'))
            ->when($request->boolean('trashed'), fn ($query) => $query->onlyTrashed())
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->has('is_available'), fn ($query) => $query->where('is_available', $request->boolean('is_available')))
            ->when($request->filled('course'), fn ($query) => $query->where('course', strtoupper((string) $request->string('course'))))
            ->when($request->has('is_featured'), fn ($query) => $query->where('is_featured', $request->boolean('is_featured')))
            ->when($request->filled('search'), fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return MenuItemResource::collection($items);
    }

    public function uploadImage(StoreMenuItemImageRequest $request, Restaurant $restaurant): JsonResponse
    {
        Gate::authorize('manageMenu', $restaurant);

        /** @var UploadedFile $image */
        $image = $request->validated('image');
        $path = $image->storePublicly("restaurants/{$restaurant->id}/menu", 'public');

        abort_if($path === false, 500, 'The dish image could not be saved.');

        return response()->json([
            'image_url' => asset("storage/{$path}"),
        ], 201);
    }

    public function store(StoreMenuItemRequest $request, Restaurant $restaurant): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        $item = $restaurant->menuItems()->create($request->safe()->except('dietary_tags'));

        $this->syncDietaryTags($item, $request->validated('dietary_tags'));

        return new MenuItemResource($item->load(['category', 'dietaryTags']));
    }

    public function show(Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        return new MenuItemResource($menuItem->load(['category', 'dietaryTags']));
    }

    public function update(UpdateMenuItemRequest $request, Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        $menuItem->update($request->safe()->except('dietary_tags'));

        if ($request->has('dietary_tags')) {
            $this->syncDietaryTags($menuItem, $request->validated('dietary_tags'));
        }

        return new MenuItemResource($menuItem->load(['category', 'dietaryTags']));
    }

    public function updateAvailability(UpdateMenuItemAvailabilityRequest $request, Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        $menuItem->update(['is_available' => $request->boolean('is_available')]);

        return new MenuItemResource($menuItem->load('dietaryTags'));
    }

    /**
     * @param  array<int, string>|null  $slugs
     */
    private function syncDietaryTags(MenuItem $item, ?array $slugs): void
    {
        if ($slugs === null) {
            return;
        }

        $ids = DietaryTag::query()
            ->whereIn('slug', $slugs)
            ->pluck('id');

        $item->dietaryTags()->sync($ids);
    }

    /**
     * Soft delete: historical order_items keep their snapshot rows.
     */
    public function destroy(Restaurant $restaurant, MenuItem $menuItem): Response
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        $menuItem->delete();

        return response()->noContent();
    }
}
