<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\MembershipRole;
use App\Enums\OrderStatus;
use App\Http\Controllers\Api\OrderController as CustomerOrderController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        $orders = $restaurant->orders()
            ->with(['items', 'deliveryDetail', 'customer', 'reservation'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('order_type'), fn ($query) => $query->where('order_type', $request->string('order_type')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->when($request->boolean('active'), fn ($query) => $query->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'REJECTED', 'REFUNDED']))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return OrderResource::collection($orders);
    }

    public function show(Restaurant $restaurant, Order $order): OrderResource
    {
        Gate::authorize('view', $restaurant);

        abort_unless($order->restaurant_id === $restaurant->id, 404);

        return new OrderResource($order->load(CustomerOrderController::DETAIL_RELATIONS));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Restaurant $restaurant, Order $order, OrderService $orders): OrderResource
    {
        Gate::authorize('view', $restaurant);

        abort_unless($order->restaurant_id === $restaurant->id, 404);

        $asAdministrator = $request->user()->isPlatformAdministrator()
            || $restaurant->hasMember($request->user(), MembershipRole::Admin);

        $order = $orders->transition(
            $order,
            OrderStatus::from($request->string('status')->toString()),
            $request->user(),
            $request->string('reason')->toString() ?: null,
            $asAdministrator,
        );

        return new OrderResource($order);
    }
}
