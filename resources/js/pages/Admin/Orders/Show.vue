<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppButton from '@/components/mori/AppButton.vue';
import AppModal from '@/components/mori/AppModal.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatDateTime, formatMoney, statusLabel } from '@/lib/format';
import type { Order, OrderStatus } from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
    orderId: number;
}>();

/**
 * Mirrors OrderStatus::staffTransitions() and administratorTransitions().
 * The API still enforces the real rules; this only decides what to offer.
 */
const staffTransitions: Partial<Record<OrderStatus, OrderStatus[]>> = {
    PLACED: ['CONFIRMED', 'REJECTED', 'CANCELLED'],
    CONFIRMED: ['PREPARING'],
    PREPARING: ['READY'],
    READY: ['COMPLETED', 'OUT_FOR_DELIVERY'],
    OUT_FOR_DELIVERY: ['COMPLETED', 'DELIVERY_FAILED'],
    DELIVERY_FAILED: ['OUT_FOR_DELIVERY'],
};

const administratorTransitions: Partial<Record<OrderStatus, OrderStatus[]>> = {
    CONFIRMED: ['CANCELLED'],
    PREPARING: ['CANCELLED'],
    READY: ['CANCELLED'],
    OUT_FOR_DELIVERY: ['CANCELLED'],
    DELIVERY_FAILED: ['CANCELLED'],
    COMPLETED: ['REFUNDED'],
};

const order = ref<Order | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const updating = ref(false);
const reason = ref('');
const pendingStatus = ref<OrderStatus | null>(null);
const confirmOpen = ref(false);

const recordingPayment = ref(false);
const paymentForm = reactive({
    method: 'CASH' as 'CASH' | 'CARD' | 'ONLINE',
    amount: '',
    provider_ref: '',
});

const nextStatuses = computed<OrderStatus[]>(() => {
    if (!order.value) {
        return [];
    }

    const staff = staffTransitions[order.value.status] ?? [];
    const admin = administratorTransitions[order.value.status] ?? [];

    return Array.from(new Set([...staff, ...admin])).filter((status) => {
        // A pickup order in READY goes straight to COMPLETED; the API rejects
        // OUT_FOR_DELIVERY for it, so keep it out of the menu.
        if (
            status === 'OUT_FOR_DELIVERY' &&
            order.value?.order_type !== 'DELIVERY'
        ) {
            return false;
        }

        return true;
    });
});

const paidAmount = computed(() =>
    (order.value?.payments ?? [])
        .filter((payment) => payment.status === 'PAID')
        .reduce((sum, payment) => sum + Number.parseFloat(payment.amount), 0),
);

const outstanding = computed(() =>
    order.value
        ? Math.max(
              0,
              Number.parseFloat(order.value.totals.total) - paidAmount.value,
          )
        : 0,
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        order.value = (
            await api.get<{ data: Order }>(
                `/admin/restaurants/${props.restaurant.id}/orders/${props.orderId}`,
            )
        ).data;

        paymentForm.amount = outstanding.value.toFixed(2);
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

function askTransition(status: OrderStatus): void {
    pendingStatus.value = status;
    reason.value = '';
    confirmOpen.value = true;
}

async function applyTransition(): Promise<void> {
    if (!pendingStatus.value) {
        return;
    }

    updating.value = true;

    try {
        order.value = (
            await api.patch<{ data: Order }>(
                `/admin/restaurants/${props.restaurant.id}/orders/${props.orderId}/status`,
                {
                    status: pendingStatus.value,
                    reason: reason.value.trim() || null,
                },
            )
        ).data;

        toast.success(`Order is now ${statusLabel(pendingStatus.value)}.`);
        confirmOpen.value = false;
    } catch (caught) {
        if (caught instanceof ApiError) {
            toast.error(errorMessage(caught));
        }
    } finally {
        updating.value = false;
    }
}

async function recordPayment(): Promise<void> {
    recordingPayment.value = true;

    try {
        await api.post(
            `/admin/restaurants/${props.restaurant.id}/orders/${props.orderId}/payments`,
            {
                method: paymentForm.method,
                amount: Number.parseFloat(paymentForm.amount),
                status: 'PAID',
                provider_ref: paymentForm.provider_ref.trim() || null,
            },
        );

        toast.success('Payment recorded.');
        paymentForm.provider_ref = '';
        await load();
    } catch (caught) {
        toast.error(errorMessage(caught));
    } finally {
        recordingPayment.value = false;
    }
}

const refunding = ref<number | null>(null);

async function refund(paymentId: number): Promise<void> {
    refunding.value = paymentId;

    try {
        await api.post(`/admin/payments/${paymentId}/refund`);
        toast.success('Payment refunded.');
        await load();
    } catch (caught) {
        toast.error(errorMessage(caught));
    } finally {
        refunding.value = null;
    }
}

onMounted(() => void load());
</script>

<template>
    <Head :title="order ? `${order.order_number} · Admin` : 'Order · Admin'" />

    <AdminTopbar
        :title="order?.order_number ?? 'Order'"
        :subtitle="restaurant.name"
    />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <Link
            href="/admin/orders"
            class="inline-flex items-center gap-1.5 text-sm text-mori-muted hover:text-mori-wine"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            All orders
        </Link>

        <p
            v-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="mt-4 space-y-4" aria-busy="true">
            <div
                class="h-24 animate-pulse rounded-lg border border-mori-line bg-mori-paper"
            />
            <div
                class="h-64 animate-pulse rounded-lg border border-mori-line bg-mori-paper"
            />
        </div>

        <EmptyState
            v-else-if="!order"
            class="mt-5"
            title="Order not found"
            description="It may belong to another restaurant or have been removed."
        />

        <template v-else>
            <header
                class="mt-4 flex flex-wrap items-start justify-between gap-4 rounded border border-mori-line bg-mori-paper p-5"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <StatusBadge :status="order.status" />
                        <StatusBadge :status="order.payment_status" />
                        <span
                            class="text-xs tracking-wide text-mori-muted uppercase"
                        >
                            {{ statusLabel(order.order_type) }}
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-mori-muted">
                        Placed {{ formatDateTime(order.created_at) }}
                        <template v-if="order.customer">
                            by {{ order.customer.name }}
                        </template>
                    </p>
                    <p class="mt-1 font-display text-xl text-mori-ink">
                        {{ formatMoney(order.totals.total) }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <AppButton
                        variant="outline"
                        size="sm"
                        :icon="RefreshCw"
                        @click="load"
                    >
                        Refresh
                    </AppButton>
                    <AppButton
                        v-for="status in nextStatuses"
                        :key="status"
                        :variant="
                            status === 'CANCELLED' || status === 'REJECTED'
                                ? 'outline'
                                : 'primary'
                        "
                        size="sm"
                        @click="askTransition(status)"
                    >
                        {{ statusLabel(status) }}
                    </AppButton>
                </div>
            </header>

            <div
                class="mt-4 grid min-w-0 gap-4 lg:grid-cols-[minmax(0,1fr)_21.25rem]"
            >
                <div class="min-w-0 space-y-4">
                    <section class="report-card">
                        <h2
                            class="report-card-title border-b border-mori-line px-5 py-3"
                        >
                            Items
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[20rem] text-sm">
                                <tbody>
                                    <tr
                                        v-for="item in order.items ?? []"
                                        :key="item.id"
                                        class="border-b border-mori-line/60 last:border-0"
                                    >
                                        <td class="px-5 py-3">
                                            <p
                                                class="font-medium text-mori-ink"
                                            >
                                                {{ item.name }}
                                            </p>
                                            <p
                                                v-if="item.note"
                                                class="text-xs text-mori-muted"
                                            >
                                                {{ item.note }}
                                            </p>
                                            <ul
                                                v-if="
                                                    item.selected_customizations
                                                        ?.length
                                                "
                                                class="mt-1 space-y-0.5 text-xs text-mori-muted"
                                            >
                                                <li
                                                    v-for="group in item.selected_customizations"
                                                    :key="group.group_id"
                                                >
                                                    {{ group.group_name }}:
                                                    {{
                                                        group.options
                                                            .map(
                                                                (option) =>
                                                                    option.name,
                                                            )
                                                            .join(', ')
                                                    }}
                                                </li>
                                            </ul>
                                        </td>
                                        <td
                                            class="px-3 py-3 text-right text-mori-muted"
                                        >
                                            × {{ item.quantity }}
                                        </td>
                                        <td
                                            class="px-5 py-3 text-right whitespace-nowrap"
                                        >
                                            {{ formatMoney(item.line_total) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <dl
                            class="space-y-1.5 border-t border-mori-line px-5 py-4 text-sm"
                        >
                            <div class="flex justify-between">
                                <dt class="text-mori-muted">Subtotal</dt>
                                <dd>
                                    {{ formatMoney(order.totals.subtotal) }}
                                </dd>
                            </div>
                            <div
                                v-if="Number(order.totals.delivery_fee) > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-mori-muted">Delivery</dt>
                                <dd>
                                    {{ formatMoney(order.totals.delivery_fee) }}
                                </dd>
                            </div>
                            <div
                                v-if="Number(order.totals.tax) > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-mori-muted">Tax</dt>
                                <dd>{{ formatMoney(order.totals.tax) }}</dd>
                            </div>
                            <div class="flex justify-between font-semibold">
                                <dt>Total</dt>
                                <dd class="font-display">
                                    {{ formatMoney(order.totals.total) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section v-if="order.delivery" class="report-card p-5">
                        <h2 class="report-card-title">Delivery</h2>
                        <p class="mt-2 text-sm text-mori-ink">
                            {{ order.delivery.recipient_name }} ·
                            {{ order.delivery.recipient_phone }}
                        </p>
                        <p class="mt-1 text-sm text-mori-muted">
                            {{ order.delivery.address_line }}
                        </p>
                        <p class="mt-2 text-xs text-mori-muted">
                            {{ statusLabel(order.delivery.delivery_status) }}
                            <template v-if="order.delivery.driver">
                                · {{ order.delivery.driver.name }}
                            </template>
                        </p>
                    </section>

                    <section v-if="order.customer_note" class="report-card p-5">
                        <h2 class="report-card-title">Customer note</h2>
                        <p class="mt-2 text-sm text-mori-muted">
                            {{ order.customer_note }}
                        </p>
                    </section>

                    <section class="report-card">
                        <h2
                            class="report-card-title border-b border-mori-line px-5 py-3"
                        >
                            Payments
                        </h2>

                        <ul
                            v-if="order.payments?.length"
                            class="divide-y divide-mori-line/60"
                        >
                            <li
                                v-for="payment in order.payments"
                                :key="payment.id"
                                class="flex items-center justify-between gap-3 px-5 py-3 text-sm"
                            >
                                <div>
                                    <p class="font-medium text-mori-ink">
                                        {{ formatMoney(payment.amount) }} ·
                                        {{ statusLabel(payment.method) }}
                                    </p>
                                    <p class="text-xs text-mori-muted">
                                        {{ formatDateTime(payment.created_at) }}
                                        <template v-if="payment.provider_ref">
                                            · {{ payment.provider_ref }}
                                        </template>
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <StatusBadge :status="payment.status" />
                                    <AppButton
                                        v-if="payment.status === 'PAID'"
                                        variant="ghost"
                                        size="sm"
                                        :loading="refunding === payment.id"
                                        @click="refund(payment.id)"
                                    >
                                        Refund
                                    </AppButton>
                                </div>
                            </li>
                        </ul>

                        <p v-else class="px-5 py-4 text-sm text-mori-muted">
                            No payments recorded.
                        </p>

                        <form
                            v-if="
                                !['CANCELLED', 'REJECTED', 'REFUNDED'].includes(
                                    order.status,
                                )
                            "
                            class="grid gap-3 border-t border-mori-line px-5 py-4 sm:grid-cols-4"
                            @submit.prevent="recordPayment"
                        >
                            <div class="space-y-1.5">
                                <Label for="method">Method</Label>
                                <select
                                    id="method"
                                    v-model="paymentForm.method"
                                    class="mori-field h-9 w-full"
                                >
                                    <option value="CASH">Cash</option>
                                    <option value="CARD">Card</option>
                                    <option value="ONLINE">Online</option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="amount">Amount</Label>
                                <Input
                                    id="amount"
                                    v-model="paymentForm.amount"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    :max="outstanding"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="provider_ref">Reference</Label>
                                <Input
                                    id="provider_ref"
                                    v-model="paymentForm.provider_ref"
                                />
                            </div>
                            <div class="flex items-end">
                                <AppButton
                                    type="submit"
                                    block
                                    :loading="recordingPayment"
                                    :disabled="outstanding <= 0"
                                >
                                    Record payment
                                </AppButton>
                            </div>
                        </form>
                    </section>
                </div>

                <aside class="space-y-4">
                    <section class="report-card p-5">
                        <h2 class="report-card-title">Balance</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-mori-muted">Paid</dt>
                                <dd>{{ formatMoney(paidAmount) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-mori-muted">Outstanding</dt>
                                <dd class="font-semibold">
                                    {{ formatMoney(outstanding) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="order.status_history?.length"
                        class="report-card p-5"
                    >
                        <h2 class="report-card-title">Status history</h2>
                        <ol class="mt-4 space-y-4">
                            <li
                                v-for="entry in order.status_history"
                                :key="entry.id"
                                class="relative border-l border-mori-line pl-4"
                            >
                                <span
                                    class="absolute top-1 -left-[5px] size-2 rounded-full bg-mori-wine"
                                    aria-hidden="true"
                                />
                                <p class="text-sm font-medium text-mori-ink">
                                    {{ statusLabel(entry.to_status) }}
                                </p>
                                <p class="text-xs text-mori-muted">
                                    {{ formatDateTime(entry.changed_at) }}
                                    <template v-if="entry.changed_by">
                                        · {{ entry.changed_by.name }}
                                    </template>
                                </p>
                                <p
                                    v-if="entry.reason"
                                    class="mt-1 text-xs text-mori-muted"
                                >
                                    {{ entry.reason }}
                                </p>
                            </li>
                        </ol>
                    </section>
                </aside>
            </div>
        </template>

        <AppModal
            v-model:open="confirmOpen"
            :title="`Move order to ${pendingStatus ? statusLabel(pendingStatus) : ''}?`"
            description="The change is recorded in the order's status history."
        >
            <div class="space-y-1.5">
                <Label for="reason">Reason (optional)</Label>
                <Input
                    id="reason"
                    v-model="reason"
                    maxlength="255"
                    placeholder="Customer called, kitchen backed up…"
                />
            </div>
            <template #footer>
                <AppButton variant="outline" @click="confirmOpen = false">
                    Cancel
                </AppButton>
                <AppButton :loading="updating" @click="applyTransition">
                    Confirm
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
