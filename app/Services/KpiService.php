<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\DeliveryDetail;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Restaurant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Restaurant-scoped operational aggregates.
 *
 * Revenue definitions (documented in docs/api-spec.md):
 *   - revenue counts orders whose payment_status is PAID
 *   - orders in CANCELLED, REJECTED or REFUNDED never count as revenue
 *   - unpaid/COD orders are not revenue even when completed
 * Day boundaries use config('app.timezone').
 */
class KpiService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Restaurant $restaurant, CarbonInterface $from, CarbonInterface $to): array
    {
        $base = fn (): Builder => Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to]);

        $revenueQuery = fn (): Builder => $base()
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotIn('status', OrderStatus::revenueExcluded());

        $todayFrom = now()->startOfDay();
        $todayTo = now()->endOfDay();

        $revenue = (float) $revenueQuery()->sum('total');
        $paidOrderCount = $revenueQuery()->count();

        $todayRevenue = (float) $base()
            ->whereBetween('created_at', [$todayFrom, $todayTo])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotIn('status', OrderStatus::revenueExcluded())
            ->sum('total');

        $orderCount = $base()->count();
        $completedCount = $base()->where('status', OrderStatus::Completed)->count();

        $activeStatuses = [
            OrderStatus::Placed, OrderStatus::Confirmed, OrderStatus::Preparing,
            OrderStatus::Ready, OrderStatus::OutForDelivery, OrderStatus::DeliveryFailed,
        ];

        $activeOrders = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('status', $activeStatuses)
            ->count();

        $todayOrders = $base()->whereBetween('created_at', [$todayFrom, $todayTo])->count();

        $typeCounts = $base()
            ->selectRaw('order_type, COUNT(*) as aggregate')
            ->groupBy('order_type')
            ->pluck('aggregate', 'order_type');

        $reservationCounts = Reservation::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('start_time', [$from, $to])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $reservationTotal = (int) $reservationCounts->sum();

        $activeDeliveries = DeliveryDetail::query()
            ->whereIn('delivery_status', ['UNASSIGNED', 'ASSIGNED', 'PICKED_UP'])
            ->whereHas('order', fn (Builder $query) => $query->where('restaurant_id', $restaurant->id))
            ->count();

        return [
            'range' => [
                'from' => Carbon::instance($from)->toDateString(),
                'to' => Carbon::instance($to)->toDateString(),
            ],
            'revenue' => round($revenue, 2),
            'revenue_today' => round($todayRevenue, 2),
            'orders_today' => $todayOrders,
            'order_count' => $orderCount,
            'completed_order_count' => $completedCount,
            'average_order_value' => $paidOrderCount > 0 ? round($revenue / $paidOrderCount, 2) : 0,
            'pending_orders' => $activeOrders,
            'pickup_orders' => (int) ($typeCounts[OrderType::Pickup->value] ?? 0),
            'delivery_orders' => (int) ($typeCounts[OrderType::Delivery->value] ?? 0),
            'reservation_count' => $reservationTotal,
            'completed_reservations' => (int) ($reservationCounts[ReservationStatus::Completed->value] ?? 0),
            'no_show_reservations' => (int) ($reservationCounts[ReservationStatus::NoShow->value] ?? 0),
            'no_show_rate' => $reservationTotal > 0
                ? round(((int) ($reservationCounts[ReservationStatus::NoShow->value] ?? 0)) / $reservationTotal * 100, 2)
                : 0,
            'active_deliveries' => $activeDeliveries,
            'revenue_series' => $this->revenueSeries($restaurant, $from, $to),
            'order_series' => $this->orderSeries($restaurant, $from, $to),
            'recent_orders' => $this->recentOrders($restaurant),
        ];
    }

    /**
     * @return array<int, array{date: string, revenue: float}>
     */
    private function revenueSeries(Restaurant $restaurant, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->where('payment_status', PaymentStatus::Paid)
            ->whereNotIn('status', OrderStatus::revenueExcluded())
            ->selectRaw('DATE(created_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('revenue', 'day');

        return collect($this->dateKeys($from, $to))
            ->map(fn (string $day): array => [
                'date' => $day,
                'revenue' => round((float) ($rows[$day] ?? 0), 2),
            ])
            ->all();
    }

    /**
     * @return array<int, array{date: string, count: int}>
     */
    private function orderSeries(Restaurant $restaurant, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day');

        return collect($this->dateKeys($from, $to))
            ->map(fn (string $day): array => [
                'date' => $day,
                'count' => (int) ($rows[$day] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(Restaurant $restaurant): array
    {
        return Order::query()
            ->where('restaurant_id', $restaurant->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'order_number', 'order_type', 'status', 'payment_status', 'total', 'created_at'])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'order_type' => $order->order_type->value,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'total' => (string) $order->total,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function dateKeys(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $cursor = Carbon::instance($from)->startOfDay();
        $last = Carbon::instance($to)->startOfDay();
        $keys = collect();

        while ($cursor->lessThanOrEqualTo($last)) {
            $keys->push($cursor->toDateString());
            $cursor = $cursor->addDay();
        }

        return $keys;
    }

    /**
     * Payments captured for a restaurant range, used by the finance view.
     */
    public function paymentTotals(Restaurant $restaurant, CarbonInterface $from, CarbonInterface $to): float
    {
        return (float) Payment::query()
            ->where('status', PaymentStatus::Paid)
            ->whereBetween('paid_at', [$from, $to])
            ->whereHas('order', fn (Builder $query) => $query->where('restaurant_id', $restaurant->id))
            ->sum('amount');
    }
}
