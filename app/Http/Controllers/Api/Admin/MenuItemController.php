<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMenuItemRequest;
use App\Http\Requests\Api\UpdateMenuItemAvailabilityRequest;
use App\Http\Requests\Api\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MenuItemController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('manageMenu', $restaurant);

        $items = $restaurant->menuItems()
            ->with('category')
            ->withTrashed()
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->has('is_available'), fn ($query) => $query->where('is_available', $request->boolean('is_available')))
            ->when($request->has('is_featured'), fn ($query) => $query->where('is_featured', $request->boolean('is_featured')))
            ->when($request->filled('search'), fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search').'%'))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return MenuItemResource::collection($items);
    }

    public function store(StoreMenuItemRequest $request, Restaurant $restaurant): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        return new MenuItemResource(
            $restaurant->menuItems()->create($request->validated())->load('category'),
        );
    }

    public function show(Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        return new MenuItemResource($menuItem->load('category'));
    }

    public function update(UpdateMenuItemRequest $request, Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        $menuItem->update($request->validated());

        return new MenuItemResource($menuItem->load('category'));
    }

    public function updateAvailability(UpdateMenuItemAvailabilityRequest $request, Restaurant $restaurant, MenuItem $menuItem): MenuItemResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($menuItem->restaurant_id === $restaurant->id, 404);

        $menuItem->update(['is_available' => $request->boolean('is_available')]);

        return new MenuItemResource($menuItem);
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
