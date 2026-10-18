<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import OrdersTable from '@/components/mori/OrdersTable.vue';
import { Input } from '@/components/ui/input';
import { api, errorMessage } from '@/lib/api';
import type {
    Order,
    OrderStatus,
    OrderType,
    Paginated,
    PaymentStatus,
} from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const orders = ref<Order[]>([]);
const meta = ref<Paginated<Order>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const filters = ref({
    status: '' as OrderStatus | '',
    order_type: '' as OrderType | '',
    payment_status: '' as PaymentStatus | '',
    from: '',
    to: '',
    active: false,
    page: 1,
});

const statuses: (OrderStatus | '')[] = [
    '',
    'PLACED',
    'CONFIRMED',
    'PREPARING',
    'READY',
    'OUT_FOR_DELIVERY',
    'COMPLETED',
    'CANCELLED',
    'REJECTED',
    'DELIVERY_FAILED',
    'REFUNDED',
];

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<Order>>(
            `/admin/restaurants/${props.restaurant.id}/orders`,
            {
                query: {
                    status: filters.value.status || undefined,
                    order_type: filters.value.order_type || undefined,
                    payment_status: filters.value.payment_status || undefined,
                    from: filters.value.from || undefined,
                    to: filters.value.to || undefined,
                    active: filters.value.active || undefined,
                    page: filters.value.page,
                    per_page: 15,
                },
            },
        );

        orders.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

watch(
    () => [
        filters.value.status,
        filters.value.order_type,
        filters.value.payment_status,
        filters.value.from,
        filters.value.to,
        filters.value.active,
    ],
    () => {
        filters.value.page = 1;
        void load();
    },
);

watch(
    () => filters.value.page,
    () => void load(),
);

onMounted(() => void load());
</script>

<template>
    <Head title="Orders · MORI Admin" />

    <AdminTopbar title="Order management" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <div
            class="admin-filter-bar flex flex-wrap items-end gap-3 rounded border border-mori-line bg-mori-paper p-4"
        >
            <div class="min-w-40 space-y-1.5">
                <label for="status" class="mori-field-label"> Status </label>
                <select
                    id="status"
                    v-model="filters.status"
                    class="mori-field h-9 w-full"
                >
                    <option
                        v-for="option in statuses"
                        :key="option || 'all'"
                        :value="option"
                    >
                        {{
                            option ? option.replace(/_/g, ' ') : 'All statuses'
                        }}
                    </option>
                </select>
            </div>

            <div class="min-w-36 space-y-1.5">
                <label for="order_type" class="mori-field-label"> Type </label>
                <select
                    id="order_type"
                    v-model="filters.order_type"
                    class="mori-field h-9 w-full"
                >
                    <option value="">All types</option>
                    <option value="PICKUP">Pickup</option>
                    <option value="DELIVERY">Delivery</option>
                </select>
            </div>

            <div class="min-w-36 space-y-1.5">
                <label for="payment_status" class="mori-field-label">
                    Payment
                </label>
                <select
                    id="payment_status"
                    v-model="filters.payment_status"
                    class="mori-field h-9 w-full"
                >
                    <option value="">Any</option>
                    <option value="UNPAID">Unpaid</option>
                    <option value="PAID">Paid</option>
                    <option value="FAILED">Failed</option>
                    <option value="REFUNDED">Refunded</option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="from" class="mori-field-label"> From </label>
                <Input
                    id="from"
                    v-model="filters.from"
                    type="date"
                    class="h-9"
                />
            </div>

            <div class="space-y-1.5">
                <label for="to" class="mori-field-label"> To </label>
                <Input id="to" v-model="filters.to" type="date" class="h-9" />
            </div>

            <label class="flex items-center gap-2 pb-1.5 text-sm text-mori-ink">
                <input v-model="filters.active" type="checkbox" />
                Open only
            </label>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div class="mt-4">
            <OrdersTable :orders="orders" :loading="loading" />
        </div>

        <div
            v-if="meta && meta.last_page > 1"
            class="mt-4 flex items-center justify-between gap-3 text-sm"
        >
            <p class="text-mori-muted">
                Showing {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
            </p>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
                    :disabled="meta.current_page <= 1"
                    @click="filters.page = meta.current_page - 1"
                >
                    Previous
                </button>
                <button
                    type="button"
                    class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="filters.page = meta.current_page + 1"
                >
                    Next
                </button>
            </div>
        </div>
    </div>
</template>
