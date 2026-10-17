<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateDeliveryRequest;
use App\Http\Resources\DeliveryDetailResource;
use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeliveryController extends Controller
{
    public function index(Request $request, Restaurant $restaurant): AnonymousResourceCollection
    {
        Gate::authorize('view', $restaurant);

        $deliveries = DeliveryDetail::query()
            ->whereHas('order', fn ($query) => $query->where('restaurant_id', $restaurant->id))
            ->with(['driver', 'order'])
            ->when($request->filled('delivery_status'), fn ($query) => $query->where('delivery_status', $request->string('delivery_status')))
            ->when($request->boolean('active'), fn ($query) => $query->whereNotIn('delivery_status', [
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Failed->value,
            ]))
            ->when($request->filled('driver_id'), fn ($query) => $query->where('driver_id', $request->integer('driver_id')))
            ->latest()
            ->paginate(min(max($request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return DeliveryDetailResource::collection($deliveries);
    }

    public function show(Restaurant $restaurant, DeliveryDetail $delivery): DeliveryDetailResource
    {
        Gate::authorize('view', $delivery);

        abort_unless($delivery->order->restaurant_id === $restaurant->id, 404);

        return new DeliveryDetailResource($delivery->load(['driver', 'order']));
    }

    /**
     * Advance the delivery record and mirror the change onto the order status
     * so the kitchen/delivery boards stay consistent.
     */
    public function update(UpdateDeliveryRequest $request, Restaurant $restaurant, DeliveryDetail $delivery): DeliveryDetailResource
    {
        Gate::authorize('update', $delivery);

        abort_unless($delivery->order->restaurant_id === $restaurant->id, 404);

        $data = $request->validated();

        DB::transaction(function () use ($delivery, $data): void {
            $delivery->refresh();

            if (array_key_exists('driver_id', $data)) {
                $driverId = $data['driver_id'];

                if ($driverId !== null) {
                    $this->assertEligibleDriver((int) $driverId, $delivery->order);
                }

                $delivery->driver_id = $driverId;
                $delivery->assigned_at = $driverId !== null ? ($delivery->assigned_at ?? now()) : null;
            }

            if (isset($data['delivery_status'])) {
                $next = DeliveryStatus::from($data['delivery_status']);

                if ($next !== $delivery->delivery_status && ! $delivery->delivery_status->canTransitionTo($next)) {
                    throw ApiException::invalid(
                        "Cannot move delivery from {$delivery->delivery_status->value} to {$next->value}.",
                        ['delivery_status' => ['This status transition is not allowed.']],
                    );
                }

                $delivery->delivery_status = $next;

                $delivery->picked_up_at = $next === DeliveryStatus::PickedUp
                    ? ($delivery->picked_up_at ?? now())
                    : $delivery->picked_up_at;

                $delivery->delivered_at = $next === DeliveryStatus::Delivered ? now() : $delivery->delivered_at;

                $delivery->failure_reason = $next === DeliveryStatus::Failed
                    ? ($data['failure_reason'] ?? $delivery->failure_reason)
                    : null;
            }

            $delivery->save();

            $this->syncOrderStatus($delivery);
        });

        return new DeliveryDetailResource($delivery->fresh(['driver', 'order']));
    }

    private function assertEligibleDriver(int $driverId, Order $order): void
    {
        $driver = User::query()->find($driverId);

        if ($driver === null) {
            throw ApiException::invalid('Driver not found.', ['driver_id' => ['Unknown user.']]);
        }

        $isMember = $order->restaurant->hasMember($driver);

        if (! $isMember && ! $driver->isPlatformAdministrator()) {
            throw ApiException::invalid(
                'The driver is not a member of this restaurant.',
                ['driver_id' => ['Driver must be restaurant staff.']],
            );
        }
    }

    private function syncOrderStatus(DeliveryDetail $delivery): void
    {
        $order = $delivery->order;

        if ($order->order_type !== OrderType::Delivery) {
            return;
        }

        $target = match ($delivery->delivery_status) {
            DeliveryStatus::PickedUp => 'OUT_FOR_DELIVERY',
            DeliveryStatus::Delivered => 'COMPLETED',
            DeliveryStatus::Failed => 'DELIVERY_FAILED',
            default => null,
        };

        if ($target === null || $order->status->value === $target) {
            return;
        }

        // Delegate to the order engine so history rows and transition rules apply.
        app(OrderService::class)->transition(
            $order,
            OrderStatus::from($target),
            auth()->user(),
            'Delivery status updated',
            asAdministrator: true,
        );
    }
}
