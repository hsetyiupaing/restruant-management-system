<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Resources\MenuItemResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Support\OpeningHours;
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
            ->with('category')
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
        ]);
    }

    /**
     * The storefront is a single-venue site: prefer the configured slug, then
     * fall back to any active restaurant so a fresh database still renders.
     */
    private function featuredRestaurant(): ?Restaurant
    {
        $slug = config('restaurant.storefront_slug');

        $restaurant = Restaurant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        return $restaurant
            ?? Restaurant::query()->where('is_active', true)->orderBy('name')->first();
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
