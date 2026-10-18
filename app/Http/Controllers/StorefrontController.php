<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\DietaryTagResource;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\DietaryTag;
use App\Models\Restaurant;
use App\Support\OpeningHours;
use App\Support\RestaurantContext;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public storefront pages. Restaurant and menu data reach the Vue pages here;
 * cart, checkout and reservations go straight to the v1 API from the client.
 */
class StorefrontController extends Controller
{
    public function home(): Response
    {
        $restaurant = $this->featuredRestaurant();

        if ($restaurant === null) {
            return Inertia::render('Storefront/Home', [
                'restaurant' => null,
                'featuredDishes' => [],
                'openingHours' => null,
                'totalDishes' => 0,
            ]);
        }

        $featured = $restaurant->menuItems()
            ->with(['category', 'dietaryTags'])
            ->available()
            ->where('is_featured', true)
            ->orderBy('name')
            ->limit(6)
            ->get();

        return Inertia::render('Storefront/Home', [
            'restaurant' => (new RestaurantResource($restaurant))->resolve(),
            'featuredDishes' => MenuItemResource::collection($featured)->resolve(),
            'openingHours' => $this->openingHours($restaurant),
            'totalDishes' => $restaurant->menuItems()->available()->count(),
        ]);
    }

    public function menu(): Response
    {
        $restaurant = $this->featuredRestaurant();

        if ($restaurant === null) {
            return Inertia::render('Storefront/Menu', [
                'restaurant' => null,
                'categories' => [],
                'dietaryTags' => [],
            ]);
        }

        $categories = $restaurant->categories()
            ->withCount(['menuItems' => fn ($query) => $query->available()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('Storefront/Menu', [
            'restaurant' => (new RestaurantResource($restaurant))->resolve(),
            'categories' => CategoryResource::collection($categories)->resolve(),
            // The chips filter through the API, but the vocabulary itself is
            // small and fixed, so it is rendered with the page.
            'dietaryTags' => DietaryTagResource::collection(
                DietaryTag::query()->orderBy('name')->get(),
            )->resolve(),
        ]);
    }

    public function dish(string $dish): Response
    {
        $restaurant = $this->featuredRestaurant();

        return Inertia::render('Storefront/Dish', [
            'restaurant' => $restaurant === null
                ? null
                : (new RestaurantResource($restaurant))->resolve(),
            // The page resolves the dish from the public API so it shares one
            // contract with the menu listing; an unknown slug renders the
            // page's not-found state rather than a bare error page.
            'dish' => $dish,
        ]);
    }

    /**
     * The storefront is a single-venue site. Shared with the back office so
     * both sides of the application always show the same restaurant.
     */
    private function featuredRestaurant(): ?Restaurant
    {
        return RestaurantContext::storefrontRestaurant();
    }

    /**
     * @return array<string, string>|null
     */
    private function openingHours(Restaurant $restaurant): ?array
    {
        $hours = new OpeningHours($restaurant->opening_hours);

        if ($hours->isAlwaysOpen()) {
            return null;
        }

        return $restaurant->opening_hours;
    }
}
