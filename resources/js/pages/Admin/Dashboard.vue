<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Bike,
    CalendarDays,
    CircleDollarSign,
    ClipboardList,
    RefreshCw,
    ShoppingBag,
} from '@lucide/vue';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import DataTable from '@/components/mori/DataTable.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { api, errorMessage } from '@/lib/api';
import { formatDate } from '@/lib/format';
import type { Kpis } from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const kpis = ref<Kpis | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const statCards = computed(() => {
    const data = kpis.value;

    if (!data) {
        return [];
    }

    return [
        {
            label: 'Revenue',
            value: formatCurrency(data.revenue),
            hint: `${formatCurrency(data.revenue_today)} today`,
            icon: CircleDollarSign,
        },
        {
            label: 'Orders',
            value: String(data.order_count),
            hint: `${data.orders_today} today · ${data.pending_orders} open`,
            icon: ShoppingBag,
        },
        {
            label: 'Average order',
            value: formatCurrency(data.average_order_value),
            hint: `${data.completed_order_count} completed`,
            icon: ClipboardList,
        },
        {
            label: 'Reservations',
            value: String(data.reservation_count),
            hint: `${data.no_show_rate}% no-show rate`,
            icon: CalendarDays,
        },
        {
            label: 'Active deliveries',
            value: String(data.active_deliveries),
            hint: `${data.delivery_orders} delivery orders`,
            icon: Bike,
        },
    ];
});

const chartMax = computed(() => {
    const values = kpis.value?.revenue_series.map((point) => point.revenue) ?? [];

    return Math.max(1, ...values);
});

const channelTotal = computed(
    () => (kpis.value?.pickup_orders ?? 0) + (kpis.value?.delivery_orders ?? 0),
);

const pickupShare = computed(() =>
    channelTotal.value === 0
        ? 0
        : Math.round(((kpis.value?.pickup_orders ?? 0) / channelTotal.value) * 100),
);

const recentRows = computed(
    () => (kpis.value?.recent_orders ?? []) as unknown as Record<string, unknown>[],
);

const recentColumns = [
    { key: 'order_number', label: 'Order' },
    { key: 'order_type', label: 'Type' },
    { key: 'status', label: 'Status' },
    { key: 'total', label: 'Total', align: 'right' as const },
];

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB',
        maximumFractionDigits: 0,
    }).format(value);
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<{ data: Kpis }>(
            `/admin/restaurants/${props.restaurant.id}/kpis`,
        );

        kpis.value = response.data;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

onMounted(() => void load());
</script>

<template>
    <Head title="Dashboard · MORI Admin" />

    <AdminTopbar title="Dashboard" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-mori-muted">
                <template v-if="kpis">
                    {{ formatDate(kpis.range.from) }} – {{ formatDate(kpis.range.to) }}
                </template>
                <template v-else>Last 30 days</template>
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded border border-[#e3e5df] bg-white px-3 py-2 text-xs font-medium text-mori-ink hover:bg-mori-cream"
                :disabled="loading"
                @click="load"
            >
                <RefreshCw class="size-3.5" aria-hidden="true" />
                Refresh
            </button>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div
                v-for="index in 5"
                :key="index"
                class="h-28 animate-pulse rounded border border-[#e3e5df] bg-white"
            />
        </div>

        <template v-else-if="kpis">
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div
                    v-for="card in statCards"
                    :key="card.label"
                    class="rounded border border-[#e3e5df] bg-white p-4"
                >
                    <div class="flex items-center justify-between">
                        <p class="text-xs tracking-wide text-mori-muted uppercase">
                            {{ card.label }}
                        </p>
                        <component
                            :is="card.icon"
                            class="size-4 text-mori-muted"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="mt-2 font-display text-2xl text-mori-ink">
                        {{ card.value }}
                    </p>
                    <p class="mt-1 text-xs text-mori-muted">{{ card.hint }}</p>
                </div>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_320px]">
                <section class="rounded border border-[#e3e5df] bg-white p-5">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        Revenue
                    </h2>
                    <p class="mt-1 text-xs text-mori-muted">
                        Paid orders only. Cancelled, rejected and refunded orders
                        are excluded.
                    </p>

                    <div
                        v-if="kpis.revenue_series.length"
                        class="mt-5 flex h-48 items-end gap-1"
                        role="img"
                        :aria-label="`Daily revenue for ${kpis.revenue_series.length} days`"
                    >
                        <div
                            v-for="point in kpis.revenue_series"
                            :key="point.date"
                            class="group relative flex-1 rounded-t bg-mori-sage/30 transition-colors hover:bg-mori-sage/60"
                            :style="{
                                height: `${Math.max(2, (point.revenue / chartMax) * 100)}%`,
                            }"
                        >
                            <span
                                class="pointer-events-none absolute -top-8 left-1/2 hidden -translate-x-1/2 rounded bg-mori-ink px-2 py-1 text-[10px] whitespace-nowrap text-white group-hover:block"
                            >
                                {{ formatDate(point.date) }} ·
                                {{ formatCurrency(point.revenue) }}
                            </span>
                        </div>
                    </div>

                    <p v-else class="mt-5 text-sm text-mori-muted">
                        No revenue in this period.
                    </p>
                </section>

                <section class="rounded border border-[#e3e5df] bg-white p-5">
                    <h2 class="text-sm font-semibold text-mori-ink">Channels</h2>
                    <p class="mt-1 text-xs text-mori-muted">
                        Order mix for the period.
                    </p>

                    <div v-if="channelTotal" class="mt-6">
                        <div
                            class="flex h-3 overflow-hidden rounded-full bg-mori-cream"
                            role="img"
                            :aria-label="`${pickupShare}% pickup, ${100 - pickupShare}% delivery`"
                        >
                            <div
                                class="bg-mori-wine"
                                :style="{ width: `${pickupShare}%` }"
                            />
                            <div
                                class="bg-mori-sand"
                                :style="{ width: `${100 - pickupShare}%` }"
                            />
                        </div>

                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="flex items-center gap-2 text-mori-muted">
                                    <span class="size-2.5 rounded-full bg-mori-wine" />
                                    Pickup
                                </dt>
                                <dd class="font-medium text-mori-ink">
                                    {{ kpis.pickup_orders }} ({{ pickupShare }}%)
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="flex items-center gap-2 text-mori-muted">
                                    <span class="size-2.5 rounded-full bg-mori-sand" />
                                    Delivery
                                </dt>
                                <dd class="font-medium text-mori-ink">
                                    {{ kpis.delivery_orders }} ({{ 100 - pickupShare }}%)
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <p v-else class="mt-6 text-sm text-mori-muted">
                        No orders in this period.
                    </p>
                </section>
            </div>

            <section class="mt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        Recent orders
                    </h2>
                    <Link
                        href="/admin/orders"
                        class="inline-flex items-center gap-1 text-xs font-medium text-mori-wine hover:underline"
                    >
                        All orders
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </Link>
                </div>

                <DataTable
                    v-if="recentRows.length"
                    :columns="recentColumns"
                    :rows="recentRows"
                    row-key="id"
                    striped
                >
                    <template #cell-order_number="{ row }">
                        <Link
                            :href="`/admin/orders/${row.id}`"
                            class="font-medium text-mori-ink hover:underline"
                        >
                            {{ row.order_number }}
                        </Link>
                    </template>
                    <template #cell-order_type="{ row }">
                        <span class="text-xs tracking-wide text-mori-muted uppercase">
                            {{ row.order_type }}
                        </span>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="String(row.status)" />
                    </template>
                    <template #cell-total="{ row }">
                        {{
                            new Intl.NumberFormat('th-TH', {
                                style: 'currency',
                                currency: 'THB',
                            }).format(Number(row.total))
                        }}
                    </template>
                </DataTable>

                <EmptyState
                    v-else
                    title="No orders yet"
                    description="Orders will appear here as soon as they are placed."
                />
            </section>
        </template>

        <EmptyState
            v-else-if="!error"
            class="mt-5"
            title="No dashboard data"
            description="The reporting service returned nothing for this period."
        />
    </div>
</template>
