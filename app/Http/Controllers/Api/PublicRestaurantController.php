<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AvailabilityRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

/**
 * Unauthenticated browse endpoints. Only active restaurants and available,
 * non-deleted menu items are ever exposed here.
 */
class PublicRestaurantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $restaurants = Restaurant::query()
            ->where('is_active', true)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return RestaurantResource::collection($restaurants);
    }

    public function show(string $slug): RestaurantResource
    {
        return new RestaurantResource($this->activeRestaurant($slug));
    }

    public function categories(string $slug): AnonymousResourceCollection
    {
        $restaurant = $this->activeRestaurant($slug);

        $categories = $restaurant->categories()
            ->withCount(['menuItems' => fn ($query) => $query->available()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function menuItems(Request $request, string $slug): AnonymousResourceCollection
    {
        $restaurant = $this->activeRestaurant($slug);

        $items = $restaurant->menuItems()
            ->with('category')
            ->available()
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('category'), function ($query) use ($request): void {
                $query->whereHas('category', fn ($inner) => $inner->where('name', $request->string('category')));
            })
            ->when($request->boolean('featured'), fn ($query) => $query->where('is_featured', true))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search');
                $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return MenuItemResource::collection($items);
    }

    public function menuItem(string $slug, MenuItem $menuItem): MenuItemResource
    {
        $restaurant = $this->activeRestaurant($slug);

        abort_unless($menuItem->restaurant_id === $restaurant->id && $menuItem->is_available, 404);

        return new MenuItemResource($menuItem->load('category'));
    }

    /**
     * Table map for a requested slot.
     */
    public function availability(
        AvailabilityRequest $request,
        string $slug,
        ReservationService $reservations,
    ): JsonResponse {
        $restaurant = $this->activeRestaurant($slug);

        $validated = $request->validated();
        $start = Carbon::createFromFormat('Y-m-d H:i', $validated['date'].' '.$validated['time']);

        $tables = $reservations->availability($restaurant, $start, (int) $validated['party_size']);

        return response()->json([
            'data' => [
                'restaurant' => [
                    'id' => $restaurant->id,
                    'slug' => $restaurant->slug,
                    'slot_minutes' => $restaurant->slot_minutes,
                ],
                'start_time' => $start->toIso8601String(),
                'end_time' => $start->copy()->addMinutes($restaurant->slot_minutes)->toIso8601String(),
                'tables' => $tables->values(),
            ],
            'meta' => [
                'available_count' => $tables->where('status', 'AVAILABLE')->count(),
            ],
        ]);
    }

    private function activeRestaurant(string $slug): Restaurant
    {
        return Restaurant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
