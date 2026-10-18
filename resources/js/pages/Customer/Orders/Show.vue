<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw } from '@lucide/vue';
import AppButton from '@/components/mori/AppButton.vue';
import AppModal from '@/components/mori/AppModal.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { api, errorMessage } from '@/lib/api';
import { formatDateTime, formatMoney, statusLabel } from '@/lib/format';
import type { Order } from '@/types/restaurant';
import { toast } from 'vue-sonner';

const props = defineProps<{ orderId: number }>();

const order = ref<Order | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const cancelling = ref(false);
const confirmCancel = ref(false);

const canCancel = computed(() => order.value?.status === 'PLACED');

const timeline = computed(() =>
    [...(order.value?.status_history ?? [])].sort(
        (a, b) =>
            new Date(a.changed_at ?? 0).getTime() -
            new Date(b.changed_at ?? 0).getTime(),
    ),
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        order.value = await api
            .get<{ data: Order }>(`/orders/${props.orderId}`)
            .then((response) => response.data);
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

async function cancelOrder(): Promise<void> {
    cancelling.value = true;

    try {
        const response = await api.post<{ data: Order }>(
            `/orders/${props.orderId}/cancel`,
        );

        order.value = response.data;
        confirmCancel.value = false;
        toast.success('Order cancelled.');
    } catch (caught) {
        toast.error(errorMessage(caught));
    } finally {
        cancelling.value = false;
    }
}

onMounted(() => void load());
</script>

<template>
    <Head :title="order ? `Order ${order.order_number}` : 'Order'" />

    <div class="mori-container py-12">
        <Link
            href="/orders"
            class="inline-flex items-center gap-1.5 text-sm text-mori-muted hover:text-mori-wine"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            All orders
        </Link>

        <p
            v-if="error"
            class="mt-6 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="mt-6 space-y-4" aria-busy="true">
            <div
                class="h-32 animate-pulse rounded border border-mori-line bg-mori-cream/60"
            />
            <div
                class="h-64 animate-pulse rounded border border-mori-line bg-mori-cream/60"
            />
        </div>

        <EmptyState
            v-else-if="!order"
            class="mt-8"
            title="Order not found"
            description="It may belong to another account, or the link is out of date."
        >
            <AppButton href="/orders" size="sm">Back to my orders</AppButton>
        </EmptyState>

        <template v-else>
            <header
                class="mt-4 flex flex-wrap items-start justify-between gap-4"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="mori-heading text-3xl text-mori-ink">
                            {{ order.order_number }}
                        </h1>
                        <StatusBadge :status="order.status" />
                        <StatusBadge :status="order.payment_status" />
                    </div>
                    <p class="mt-2 text-sm text-mori-muted">
                        {{ statusLabel(order.order_type) }} · placed
                        {{ formatDateTime(order.created_at) }}
                    </p>
                </div>

                <div class="flex gap-2">
                    <AppButton
                        variant="outline"
                        size="sm"
                        :icon="RefreshCw"
                        @click="load"
                    >
                        Refresh
                    </AppButton>
                    <AppButton
                        v-if="canCancel"
                        variant="outline"
                        size="sm"
                        @click="confirmCancel = true"
                    >
                        Cancel order
                    </AppButton>
                </div>
            </header>

            <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_340px]">
                <div class="space-y-6">
                    <section
                        class="rounded border border-mori-line bg-mori-paper"
                    >
                        <h2
                            class="border-b border-mori-line px-5 py-3 text-sm font-semibold text-mori-ink"
                        >
                            Items
                        </h2>
                        <ul class="divide-y divide-mori-line/70">
                            <li
                                v-for="item in order.items ?? []"
                                :key="item.id"
                                class="flex justify-between gap-4 px-5 py-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="text-sm font-medium text-mori-ink"
                                    >
                                        {{ item.quantity }} × {{ item.name }}
                                    </p>
                                    <p
                                        v-if="item.note"
                                        class="mt-0.5 text-xs text-mori-muted"
                                    >
                                        {{ item.note }}
                                    </p>
                                    <ul
                                        v-if="
                                            item.selected_customizations?.length
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
                                                        (option) => option.name,
                                                    )
                                                    .join(', ')
                                            }}
                                        </li>
                                    </ul>
                                    <p class="mt-0.5 text-xs text-mori-muted">
                                        {{ formatMoney(item.unit_price) }} each
                                    </p>
                                </div>
                                <span
                                    class="text-sm whitespace-nowrap text-mori-ink"
                                >
                                    {{ formatMoney(item.line_total) }}
                                </span>
                            </li>
                        </ul>
                    </section>

                    <section
                        v-if="order.delivery"
                        class="rounded border border-mori-line bg-mori-paper px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold text-mori-ink">
                            Delivery
                        </h2>
                        <p class="mt-2 text-sm text-mori-ink">
                            {{ order.delivery.recipient_name }} ·
                            {{ order.delivery.recipient_phone }}
                        </p>
                        <p class="mt-1 text-sm text-mori-muted">
                            {{ order.delivery.address_line }}
                        </p>
                        <p class="mt-2 text-xs text-mori-muted">
                            Status:
                            {{ statusLabel(order.delivery.delivery_status) }}
                        </p>
                    </section>

                    <section
                        v-if="order.customer_note"
                        class="rounded border border-mori-line bg-mori-paper px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold text-mori-ink">
                            Note to the kitchen
                        </h2>
                        <p class="mt-2 text-sm text-mori-muted">
                            {{ order.customer_note }}
                        </p>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section
                        class="rounded border border-mori-line bg-mori-paper p-5"
                    >
                        <h2 class="text-sm font-semibold text-mori-ink">
                            Totals
                        </h2>
                        <dl class="mt-3 space-y-2 text-sm">
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
                            <div
                                v-if="Number(order.totals.discount) > 0"
                                class="flex justify-between"
                            >
                                <dt class="text-mori-muted">Discount</dt>
                                <dd>
                                    −{{ formatMoney(order.totals.discount) }}
                                </dd>
                            </div>
                            <div
                                class="flex justify-between border-t border-mori-line pt-3 text-base font-semibold"
                            >
                                <dt>Total</dt>
                                <dd class="font-display">
                                    {{ formatMoney(order.totals.total) }}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        v-if="timeline.length"
                        class="rounded border border-mori-line bg-mori-paper p-5"
                    >
                        <h2 class="text-sm font-semibold text-mori-ink">
                            Progress
                        </h2>
                        <ol class="mt-4 space-y-4">
                            <li
                                v-for="entry in timeline"
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
            v-model:open="confirmCancel"
            title="Cancel this order?"
            description="The kitchen is notified immediately. This cannot be undone."
        >
            <template #footer>
                <AppButton variant="outline" @click="confirmCancel = false">
                    Keep order
                </AppButton>
                <AppButton
                    variant="primary"
                    :loading="cancelling"
                    @click="cancelOrder"
                >
                    Cancel order
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
