<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Services\OrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Manual payment records. No gateway is integrated, so a payment becomes PAID
 * only through this authenticated staff action — never by client input on the
 * order itself.
 */
class PaymentController extends Controller
{
    public function index(Restaurant $restaurant, Order $order): AnonymousResourceCollection
    {
        Gate::authorize('viewPayments', $order);

        abort_unless($order->restaurant_id === $restaurant->id, 404);

        return PaymentResource::collection($order->payments()->latest()->get());
    }

    public function store(
        StorePaymentRequest $request,
        Restaurant $restaurant,
        Order $order,
        OrderService $orders,
    ): PaymentResource {
        Gate::authorize('recordPayment', $order);

        abort_unless($order->restaurant_id === $restaurant->id, 404);

        if ($order->status->isTerminal()) {
            throw ApiException::invalid('Cannot record a payment against a '.$order->status->value.' order.');
        }

        $amount = round((float) $request->float('amount'), 2);
        $outstanding = $this->outstandingBalance($order);

        if ($amount > $outstanding + 0.001) {
            throw ApiException::invalid(
                'Payment exceeds the outstanding balance.',
                ['amount' => ["Outstanding balance is {$outstanding}."]],
            );
        }

        $payment = DB::transaction(function () use ($request, $order, $amount): Payment {
            $status = PaymentStatus::from($request->string('status')->toString());

            $payment = $order->payments()->create([
                'method' => PaymentMethod::from($request->string('method')->toString()),
                'amount' => $amount,
                'status' => $status,
                'provider_ref' => $request->string('provider_ref')->toString() ?: null,
                'paid_at' => $status === PaymentStatus::Paid ? now() : null,
            ]);

            return $payment;
        });

        $orders->syncPaymentStatus($order);

        return new PaymentResource($payment);
    }

    public function refund(Payment $payment, OrderService $orders): PaymentResource
    {
        Gate::authorize('refund', $payment);

        if ($payment->status !== PaymentStatus::Paid) {
            throw ApiException::invalid('Only captured payments can be refunded.');
        }

        DB::transaction(function () use ($payment): void {
            $payment->update(['status' => PaymentStatus::Refunded]);
        });

        $orders->syncPaymentStatus($payment->order);

        return new PaymentResource($payment->fresh());
    }

    private function outstandingBalance(Order $order): float
    {
        $paid = (float) $order->payments()
            ->where('status', PaymentStatus::Paid)
            ->sum('amount');

        return round((float) $order->total - $paid, 2);
    }
}
