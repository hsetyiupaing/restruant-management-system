<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateReservationStatusRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        $reservations = $restaurant->reservations()
            ->with(['table', 'customer'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('start_time', $request->date('date')))
            ->when($request->filled('from'), fn ($query) => $query->where('start_time', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('start_time', '<=', $request->date('to')->endOfDay()))
            ->when($request->filled('table_id'), fn ($query) => $query->where('table_id', $request->integer('table_id')))
            ->orderBy('start_time')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    public function show(Restaurant $restaurant, Reservation $reservation): ReservationResource
    {
        Gate::authorize('view', $restaurant);

        abort_unless($reservation->restaurant_id === $restaurant->id, 404);

        return new ReservationResource($reservation->load(['table', 'customer', 'statusHistory.changedBy']));
    }

    public function updateStatus(
        UpdateReservationStatusRequest $request,
        Restaurant $restaurant,
        Reservation $reservation,
        ReservationService $reservations,
    ): ReservationResource {
        Gate::authorize('updateStatus', $reservation);

        abort_unless($reservation->restaurant_id === $restaurant->id, 404);

        return new ReservationResource(
            $reservations->transition(
                $reservation,
                ReservationStatus::from($request->string('status')->toString()),
                $request->user(),
            ),
        );
    }
}
