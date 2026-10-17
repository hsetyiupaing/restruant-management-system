<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRestaurantRequest;
use App\Http\Requests\Api\UpdateRestaurantRequest;
use App\Http\Resources\RestaurantResource;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Platform-level restaurant management reserved for administrators.
 */
class RestaurantController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('create', Restaurant::class);

        $restaurants = Restaurant::query()
            ->when($request->filled('search'), fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('search').'%'))
            ->when($request->has('is_active'), fn ($query) => $query
                ->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return RestaurantResource::collection($restaurants);
    }

    public function store(StoreRestaurantRequest $request): RestaurantResource
    {
        Gate::authorize('create', Restaurant::class);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $restaurant = Restaurant::query()->create($data);

        return new RestaurantResource($restaurant);
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('update', $restaurant);

        $restaurant->update($request->validated());

        return new RestaurantResource($restaurant);
    }

    public function settings(Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('view', $restaurant);

        return new RestaurantResource($restaurant);
    }

    public function activate(Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('manageActivation', $restaurant);

        $restaurant->update(['is_active' => true]);

        return new RestaurantResource($restaurant);
    }

    public function deactivate(Restaurant $restaurant): RestaurantResource
    {
        Gate::authorize('manageActivation', $restaurant);

        $restaurant->update(['is_active' => false]);

        return new RestaurantResource($restaurant);
    }
}
