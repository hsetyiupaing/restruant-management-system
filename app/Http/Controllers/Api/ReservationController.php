<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Models\Restaurant;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ReservationController extends Controller
{
    /**
     * Return the logged-in user's reservation history with optional status and upcoming filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $reservations = $request->user()->reservations()
            ->with(['restaurant', 'table'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->boolean('upcoming'), fn ($query) => $query->where('start_time', '>=', now()))
            ->orderByDesc('start_time')
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return ReservationResource::collection($reservations);
    }

    /**
     * Create a new reservation for a selected table and time slot after validating restaurant rules.
     */
    public function store(StoreReservationRequest $request, ReservationService $reservations): JsonResponse
    {
        $restaurant = Restaurant::query()->findOrFail($request->integer('restaurant_id'));

        $reservation = $reservations->create(
            $request->user(),
            $restaurant,
            $request->integer('table_id'),
            Carbon::parse($request->date('start_time')),
            $request->integer('party_size'),
            $request->string('special_request')->toString() ?: null,
        );

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    /**
     * Load a single reservation after ensuring the current user has access to it.
     */
    public function show(Request $request, Reservation $reservation): ReservationResource
    {
        Gate::authorize('view', $reservation);

        return new ReservationResource($reservation->load(['restaurant', 'table', 'customer', 'statusHistory.changedBy']));
    }

    /**
     * Cancel a reservation while enforcing the booking lifecycle and cancellation rules.
     */
    public function cancel(Request $request, Reservation $reservation, ReservationService $reservations): ReservationResource
    {
        Gate::authorize('cancel', $reservation);

        return new ReservationResource(
            $reservations->transition($reservation, ReservationStatus::Cancelled, $request->user()),
        );
    }
}
