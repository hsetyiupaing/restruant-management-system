<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDiningTableRequest;
use App\Http\Requests\Api\UpdateDiningTableRequest;
use App\Http\Resources\DiningTableResource;
use App\Models\DiningTable;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DiningTableController extends Controller
{
    /**
     * Full list (not paginated): the floor-plan editor needs every table at once.
     */
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('manageMenu', $restaurant);

        $tables = $restaurant->diningTables()
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('label')
            ->get();

        return DiningTableResource::collection($tables);
    }

    public function store(StoreDiningTableRequest $request, Restaurant $restaurant): DiningTableResource
    {
        Gate::authorize('manageMenu', $restaurant);

        return new DiningTableResource($restaurant->diningTables()->create($request->validated()));
    }

    public function update(UpdateDiningTableRequest $request, Restaurant $restaurant, DiningTable $table): DiningTableResource
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($table->restaurant_id === $restaurant->id, 404);

        $table->update($request->validated());

        return new DiningTableResource($table);
    }

    /**
     * Tables with reservations are deactivated instead of deleted, because the
     * reservations table uses a RESTRICT foreign key.
     */
    public function destroy(Restaurant $restaurant, DiningTable $table): Response|JsonResponse
    {
        Gate::authorize('manageMenu', $restaurant);

        abort_unless($table->restaurant_id === $restaurant->id, 404);

        if ($table->reservations()->exists()) {
            $table->update(['is_active' => false]);

            return response()->json([
                'message' => 'Table has reservations and was deactivated instead of deleted.',
                'data' => new DiningTableResource($table),
            ]);
        }

        $table->delete();

        return response()->noContent();
    }
}
