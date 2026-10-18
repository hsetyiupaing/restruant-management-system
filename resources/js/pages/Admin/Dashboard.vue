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
import ColumnChart from '@/components/mori/ColumnChart.vue';
import DataTable from '@/components/mori/DataTable.vue';
import DonutChart from '@/components/mori/DonutChart.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import KpiTile from '@/components/mori/KpiTile.vue';
import ReportCard from '@/components/mori/ReportCard.vue';
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

/** Short currency for axis ticks; the tiles use the full figure. */
function compactCurrency(value: number): string {
    return `฿${new Intl.NumberFormat('en', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value)}`;
}

function formatCurrency(value: number): string {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB',
        maximumFractionDigits: 0,
    }).format(value);
}

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
            primary: true,
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

const revenuePoints = computed(
    () =>
        kpis.value?.revenue_series.map((point) => ({
            date: point.date,
            value: point.revenue,
        })) ?? [],
);

const orderPoints = computed(
    () =>
        kpis.value?.order_series.map((point) => ({
            date: point.date,
            value: point.count,
        })) ?? [],
);

const channelTotal = computed(
    () => (kpis.value?.pickup_orders ?? 0) + (kpis.value?.delivery_orders ?? 0),
);

const channels = computed(() => [
    {
        label: 'Pickup',
        value: kpis.value?.pickup_orders ?? 0,
        color: 'var(--mori-wine)',
    },
    {
        label: 'Delivery',
        value: kpis.value?.delivery_orders ?? 0,
        color: 'var(--mori-brass)',
    },
]);

const reservationShown = computed(() => {
    const data = kpis.value;

    if (!data) {
        return [];
    }

    return [
        {
            label: 'Completed',
            value: data.completed_reservations,
            color: 'var(--mori-wine)',
        },
        {
            label: 'No-show',
            value: data.no_show_reservations,
            color: 'var(--mori-brass)',
        },
        {
            label: 'Other',
            value: Math.max(
                0,
                data.reservation_count -
                    data.completed_reservations -
                    data.no_show_reservations,
            ),
            color: 'var(--mori-line)',
        },
    ].filter((slice) => slice.value > 0);
});

const recentRows = computed(
    () =>
        (kpis.value?.recent_orders ?? []) as unknown as Record<
            string,
            unknown
        >[],
);

const recentColumns = [
    { key: 'order_number', label: 'Order' },
    { key: 'order_type', label: 'Type' },
    { key: 'status', label: 'Status' },
    { key: 'total', label: 'Total', align: 'right' as const },
];

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
        <!-- Report toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-mori-muted">
                <template v-if="kpis">
                    {{ formatDate(kpis.range.from) }} –
                    {{ formatDate(kpis.range.to) }}
                </template>
                <template v-else>Last 30 days</template>
            </p>
            <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded border border-mori-field bg-mori-paper px-3 py-2 text-xs font-medium text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:bg-[#ede9e1] disabled:text-mori-muted"
                :disabled="loading"
                @click="load"
            >
                <RefreshCw
                    class="size-3.5"
                    :class="{ 'animate-spin': loading }"
                    aria-hidden="true"
                />
                {{ loading ? 'Refreshing…' : 'Refresh' }}
            </button>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div
            v-if="loading"
            class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
            aria-busy="true"
        >
            <div
                v-for="index in 5"
                :key="index"
                class="h-28 animate-pulse rounded-lg border border-mori-line bg-mori-paper"
            />
        </div>

        <template v-else-if="kpis">
            <!-- KPI strip -->
            <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <KpiTile
                    v-for="card in statCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :hint="card.hint"
                    :icon="card.icon"
                    :primary="card.primary ?? false"
                />
            </div>

            <!-- Analysis row -->
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <ReportCard
                    class="lg:col-span-2"
                    title="Revenue"
                    caption="Paid orders only. Cancelled, rejected and refunded orders are excluded."
                >
                    <ColumnChart
                        v-if="revenuePoints.length"
                        :points="revenuePoints"
                        :format="compactCurrency"
                        :label="`Daily revenue for ${revenuePoints.length} days`"
                    />
                    <p v-else class="py-10 text-center text-sm text-mori-muted">
                        No revenue in this period.
                    </p>
                </ReportCard>

                <ReportCard
                    title="Order channels"
                    caption="Pickup versus delivery for the period."
                >
                    <DonutChart
                        v-if="channelTotal"
                        :slices="channels"
                        label="Order channels"
                    />
                    <p v-else class="py-10 text-center text-sm text-mori-muted">
                        No orders in this period.
                    </p>
                </ReportCard>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                <ReportCard
                    title="Order volume"
                    caption="Orders placed per day."
                >
                    <ColumnChart
                        v-if="orderPoints.length"
                        :points="orderPoints"
                        :format="(value) => String(Math.round(value))"
                        :label="`Daily order count for ${orderPoints.length} days`"
                    />
                    <p v-else class="py-10 text-center text-sm text-mori-muted">
                        No orders in this period.
                    </p>
                </ReportCard>

                <ReportCard
                    title="Reservations"
                    caption="Outcome mix for the period."
                >
                    <DonutChart
                        v-if="reservationShown.length"
                        :slices="reservationShown"
                        label="Reservation outcomes"
                    />
                    <p v-else class="py-10 text-center text-sm text-mori-muted">
                        No reservations in this period.
                    </p>
                </ReportCard>
            </div>

            <!-- Detail -->
            <div class="mt-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="report-card-title">Recent orders</h2>
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
                            class="font-medium text-mori-ink underline-offset-4 hover:underline"
                        >
                            {{ row.order_number }}
                        </Link>
                    </template>
                    <template #cell-order_type="{ row }">
                        <span
                            class="text-xs tracking-wide text-mori-muted uppercase"
                        >
                            {{ row.order_type }}
                        </span>
                    </template>
                    <template #cell-status="{ row }">
                        <StatusBadge :status="String(row.status)" />
                    </template>
                    <template #cell-total="{ row }">
                        {{ formatCurrency(Number(row.total)) }}
                    </template>
                </DataTable>

                <EmptyState
                    v-else
                    title="No orders yet"
                    description="Orders will appear here as soon as they are placed."
                />
            </div>
        </template>

        <EmptyState
            v-else-if="!error"
            class="mt-5"
            title="No dashboard data"
            description="The reporting service returned nothing for this period."
        />
    </div>
</template>
