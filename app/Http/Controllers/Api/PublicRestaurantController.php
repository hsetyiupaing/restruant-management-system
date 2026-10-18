<?php

namespace App\Http\Controllers\Api;

use App\Enums\DietaryRequirement;
use App\Enums\MenuCourse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AvailabilityRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DietaryTagResource;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\DietaryTag;
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
    /**
     * List active restaurants with optional search and pagination for the public storefront.
     */
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

    /**
     * Return one active restaurant for a public catalogue or landing-page view.
     */
    public function show(string $slug): RestaurantResource
    {
        return new RestaurantResource($this->activeRestaurant($slug));
    }

    /**
     * Return the category list for a restaurant and include the number of currently available items.
     */
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

    /**
     * The dietary classifications the storefront can filter by. Served from
     * the table so the admin form and the public chips share one source.
     */
    public function dietaryTags(): AnonymousResourceCollection
    {
        return DietaryTagResource::collection(
            DietaryTag::query()->orderBy('name')->get(),
        );
    }

    /**
     * Return filtered and paginated menu items for a restaurant's public menu.
     *
     * `course` and `dietary` combine with AND: a query for MAIN plus vegan
     * returns only vegan mains.
     */
    public function menuItems(Request $request, string $slug): AnonymousResourceCollection
    {
        $restaurant = $this->activeRestaurant($slug);

        $course = MenuCourse::tryFrom((string) $request->string('course')->upper());
        $dietary = $this->requestedDietaryTags($request);

        $items = $restaurant->menuItems()
            ->with(['category', 'dietaryTags'])
            ->available()
            ->when($course !== null, fn ($query) => $query->ofCourse($course))
            ->when($dietary !== [], fn ($query) => $query->withAllDietaryTags($dietary))
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

    /**
     * Return a single active menu item only if it belongs to the requested restaurant and remains available.
     */
    public function menuItem(string $slug, MenuItem $menuItem): MenuItemResource
    {
        $restaurant = $this->activeRestaurant($slug);

        abort_unless($menuItem->restaurant_id === $restaurant->id && $menuItem->is_available, 404);

        return new MenuItemResource($menuItem->load(['category', 'dietaryTags']));
    }

    /**
     * Read the dietary filter, accepting either `dietary=vegan,keto` or a
     * repeated `dietary[]=vegan&dietary[]=keto`. Unknown slugs are dropped so
     * a stale bookmark returns results instead of an error.
     *
     * @return array<int, string>
     */
    private function requestedDietaryTags(Request $request): array
    {
        $raw = $request->input('dietary', []);
        $values = is_array($raw) ? $raw : explode(',', (string) $raw);

        return collect($values)
            ->map(fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => DietaryRequirement::tryFrom($value) !== null)
            ->unique()
            ->values()
            ->all();
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
                'available_count' => $tables->where('status', 'available')->count(),
            ],
        ]);
    }

    /**
     * Resolve an active restaurant by slug while preventing access to disabled locations.
     */
    private function activeRestaurant(string $slug): Restaurant
    {
        return Restaurant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * Clamp the public pagination size into the supported safe range.
     */
    private function perPage(Request $request): int
    {
        return min(max($request->integer('per_page', 15), 1), 100);
    }
}
