<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { PackageOpen } from '@lucide/vue';
import AppButton from '@/components/mori/AppButton.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import SectionHeading from '@/components/mori/SectionHeading.vue';
import { api, errorMessage } from '@/lib/api';
import { formatDateTime, formatMoney, statusLabel } from '@/lib/format';
import type { Order, OrderStatus, Paginated } from '@/types/restaurant';

const statuses: { value: OrderStatus | ''; label: string }[] = [
    { value: '', label: 'All orders' },
    { value: 'PLACED', label: 'Placed' },
    { value: 'CONFIRMED', label: 'Confirmed' },
    { value: 'PREPARING', label: 'Preparing' },
    { value: 'READY', label: 'Ready' },
    { value: 'OUT_FOR_DELIVERY', label: 'On the way' },
    { value: 'COMPLETED', label: 'Completed' },
    { value: 'CANCELLED', label: 'Cancelled' },
];

const orders = ref<Order[]>([]);
const meta = ref<Paginated<Order>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const status = ref<OrderStatus | ''>('');
const page = ref(1);

const activeOrders = computed(() =>
    orders.value.filter((order) =>
        ['PLACED', 'CONFIRMED', 'PREPARING', 'READY', 'OUT_FOR_DELIVERY'].includes(
            order.status,
        ),
    ),
);

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<Order>>('/orders', {
            query: {
                status: status.value || undefined,
                page: page.value,
                per_page: 10,
            },
        });

        orders.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

watch([status, page], () => void load());

onMounted(() => void load());
</script>

<template>
    <Head title="My orders · MORI" />

    <div class="mori-container py-12">
        <SectionHeading
            eyebrow="Your account"
            title="My orders"
            text="Track what is cooking now and look back at past visits."
        >
            <template #action>
                <AppButton href="/menu" variant="outline" size="sm">
                    Order again
                </AppButton>
            </template>
        </SectionHeading>

        <div class="mt-8 flex flex-wrap gap-2" role="group" aria-label="Filter by status">
            <button
                v-for="option in statuses"
                :key="option.value || 'all'"
                type="button"
                class="rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                :class="
                    status === option.value
                        ? 'border-mori-ink bg-mori-ink text-mori-paper'
                        : 'border-mori-line text-mori-muted hover:border-mori-ink'
                "
                :aria-pressed="status === option.value"
                @click="
                    status = option.value;
                    page = 1;
                "
            >
                {{ option.label }}
            </button>
        </div>

        <p
            v-if="error"
            class="mt-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="mt-8 space-y-3" aria-busy="true">
            <div
                v-for="index in 3"
                :key="index"
                class="h-24 animate-pulse rounded border border-mori-line bg-mori-cream/60"
            />
        </div>

        <template v-else-if="orders.length">
            <p
                v-if="activeOrders.length && !status"
                class="mt-6 text-sm text-mori-muted"
            >
                {{ activeOrders.length }} order{{ activeOrders.length === 1 ? '' : 's' }}
                in progress.
            </p>

            <ul class="mt-4 divide-y divide-mori-line overflow-hidden rounded border border-mori-line bg-mori-paper">
                <li v-for="order in orders" :key="order.id">
                    <Link
                        :href="`/orders/${order.id}`"
                        class="flex flex-col gap-3 px-5 py-4 transition-colors hover:bg-mori-cream/50 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium text-mori-ink">
                                    {{ order.order_number }}
                                </span>
                                <StatusBadge :status="order.status" />
                                <StatusBadge :status="order.payment_status" />
                            </div>
                            <p class="mt-1 text-sm text-mori-muted">
                                {{ statusLabel(order.order_type) }} ·
                                {{ formatDateTime(order.created_at) }}
                                <template v-if="order.restaurant">
                                    · {{ order.restaurant.name }}
                                </template>
                            </p>
                        </div>
                        <div class="text-left sm:text-right">
                            <p class="font-display text-lg text-mori-ink">
                                {{ formatMoney(order.totals.total) }}
                            </p>
                            <p class="text-xs text-mori-muted">
                                View details
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>

            <div
                v-if="meta && meta.last_page > 1"
                class="mt-6 flex items-center justify-center gap-3"
            >
                <button
                    type="button"
                    class="rounded border border-mori-line px-3 py-1.5 text-sm disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="page = meta.current_page - 1"
                >
                    Previous
                </button>
                <span class="text-sm text-mori-muted">
                    Page {{ meta.current_page }} of {{ meta.last_page }}
                </span>
                <button
                    type="button"
                    class="rounded border border-mori-line px-3 py-1.5 text-sm disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="page = meta.current_page + 1"
                >
                    Next
                </button>
            </div>
        </template>

        <EmptyState
            v-else
            class="mt-8"
            :icon="PackageOpen"
            :title="status ? 'No orders with that status' : 'No orders yet'"
            description="When you order from the menu, it will show up here with live status."
        >
            <AppButton href="/menu" size="sm">Browse the menu</AppButton>
        </EmptyState>
    </div>
</template>
