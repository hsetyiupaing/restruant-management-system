<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Customer-facing order history and checkout.
 */
class OrderController extends Controller
{
    /**
     * Return the authenticated customer's recent orders with optional status filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()
            ->with('restaurant')
            ->withCount('items')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    /**
     * Create a new checkout order from the customer's cart and restaurant selection.
     */
    public function store(StoreOrderRequest $request, OrderService $orders): JsonResponse
    {
        $restaurant = Restaurant::query()->findOrFail($request->integer('restaurant_id'));

        abort_unless($restaurant->is_active, 422, 'This restaurant is not accepting orders.');

        $reservationId = $request->integer('reservation_id') ?: null;

        if ($reservationId !== null) {
            $reservation = $request->user()->reservations()->findOrFail($reservationId);
            abort_unless($reservation->restaurant_id === $restaurant->id, 422, 'Reservation belongs to another restaurant.');
        }

        $order = $orders->create(
            $request->user(),
            $restaurant,
            $request->safe()->except('restaurant_id'),
            $request->header('Idempotency-Key'),
        );

        return (new OrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Display one order after verifying the user is allowed to see it.
     */
    public function show(Request $request, Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(self::DETAIL_RELATIONS));
    }

    /**
     * Cancel a customer's own placed order and record the status change history.
     */
    public function cancel(Request $request, Order $order, OrderService $orders): OrderResource
    {
        Gate::authorize('cancel', $order);

        $order = $orders->transition(
            $order,
            OrderStatus::Cancelled,
            $request->user(),
            $request->string('reason')->toString() ?: 'Cancelled by customer',
        );

        return new OrderResource($order);
    }

    public const DETAIL_RELATIONS = [
        'items', 'deliveryDetail', 'statusHistory.changedBy', 'restaurant', 'reservation', 'payments',
    ];
}
