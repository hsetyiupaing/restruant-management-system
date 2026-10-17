<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\OrderController as ApiOrderController;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Server-rendered entry points for the customer account pages. The pages load
 * their data from the v1 API; these routes only render the shells.
 */
class CustomerOrderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Customer/Orders/Index');
    }

    public function show(Request $request, int $order): Response
    {
        $model = $request->user()->orders()->findOrFail($order);

        Gate::authorize('view', $model);

        return Inertia::render('Customer/Orders/Show', [
            'orderId' => $model->id,
        ]);
    }

    public function checkout(Request $request): Response
    {
        return Inertia::render('Customer/Checkout', [
            'restaurant' => $this->storefrontRestaurant(),
            'orderRelations' => ApiOrderController::DETAIL_RELATIONS,
        ]);
    }

    public function reservations(): Response
    {
        return Inertia::render('Customer/Reservations/Index', [
            'restaurant' => $this->storefrontRestaurant(),
        ]);
    }

    /**
     * The storefront is a single-venue site: prefer the configured slug, then
     * fall back to any active restaurant so a fresh database still renders.
     *
     * @return array<string, mixed>|null
     */
    private function storefrontRestaurant(): ?array
    {
        $restaurant = Restaurant::query()
            ->where('slug', config('restaurant.storefront_slug'))
            ->where('is_active', true)
            ->first()
            ?? Restaurant::query()->where('is_active', true)->orderBy('name')->first();

        return $restaurant === null
            ? null
            : (new RestaurantResource($restaurant))->resolve();
    }
}
