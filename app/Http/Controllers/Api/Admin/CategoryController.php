<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCategoryRequest;
use App\Http\Requests\Api\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('manageMenu', $restaurant);

        return CategoryResource::collection(
            $restaurant->categories()
                ->withCount('menuItems')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        );
    }

    public function store(StoreCategoryRequest $request, Restaurant $restaurant): CategoryResource
    {
        Gate::authorize('manageMenu', $restaurant);

        return new CategoryResource($restaurant->categories()->create($request->validated()));
    }

    public function update(UpdateCategoryRequest $request, Restaurant $restaurant, Category $category): CategoryResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($category->restaurant_id === $restaurant->id, 404);

        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Restaurant $restaurant, Category $category): Response
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($category->restaurant_id === $restaurant->id, 404);

        // The menu_items FK is RESTRICT, so refuse rather than surface a DB error.
        abort_if(
            $category->menuItems()->exists(),
            409,
            'Move or delete this category\'s menu items before deleting it.',
        );

        $category->delete();

        return response()->noContent();
    }
}
