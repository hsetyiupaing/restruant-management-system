<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppButton from '@/components/mori/AppButton.vue';
import DataTable from '@/components/mori/DataTable.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatDate, formatTime, statusLabel } from '@/lib/format';
import type {
    Paginated,
    Reservation,
    ReservationStatus,
} from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

/**
 * Mirrors ReservationStatus::transitions().
 */
const transitions: Partial<Record<ReservationStatus, ReservationStatus[]>> = {
    PENDING: ['CONFIRMED', 'CANCELLED', 'EXPIRED'],
    CONFIRMED: ['SEATED', 'CANCELLED', 'NO_SHOW'],
    SEATED: ['COMPLETED', 'CANCELLED'],
};

const reservations = ref<Reservation[]>([]);
const meta = ref<Paginated<Reservation>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const updating = ref<number | null>(null);

const filters = ref({
    status: '' as ReservationStatus | '',
    date: '',
    page: 1,
});

const statuses: (ReservationStatus | '')[] = [
    '',
    'PENDING',
    'CONFIRMED',
    'SEATED',
    'COMPLETED',
    'CANCELLED',
    'NO_SHOW',
    'EXPIRED',
];

const columns = [
    { key: 'start_time', label: 'Slot' },
    { key: 'customer', label: 'Guest' },
    { key: 'party_size', label: 'Party', align: 'right' as const },
    { key: 'table', label: 'Table' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '', align: 'right' as const },
];

const rows = computed(
    () => reservations.value as unknown as Record<string, unknown>[],
);

function customer(row: Record<string, unknown>): {
    name?: string;
    email?: string;
} {
    return (
        (row.customer as { name?: string; email?: string } | undefined) ?? {}
    );
}

function tableLabel(row: Record<string, unknown>): string {
    return (row.table as { label?: string } | undefined)?.label ?? '—';
}

function nextStatuses(row: Record<string, unknown>): ReservationStatus[] {
    return transitions[row.status as ReservationStatus] ?? [];
}

function asReservation(row: Record<string, unknown>): Reservation {
    return row as unknown as Reservation;
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<Reservation>>(
            `/admin/restaurants/${props.restaurant.id}/reservations`,
            {
                query: {
                    status: filters.value.status || undefined,
                    date: filters.value.date || undefined,
                    page: filters.value.page,
                    per_page: 20,
                },
            },
        );

        reservations.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

async function setStatus(
    reservation: Reservation,
    status: ReservationStatus,
): Promise<void> {
    updating.value = reservation.id;

    try {
        const response = await api.patch<{ data: Reservation }>(
            `/admin/restaurants/${props.restaurant.id}/reservations/${reservation.id}/status`,
            { status },
        );

        reservations.value = reservations.value.map((entry) =>
            entry.id === reservation.id ? response.data : entry,
        );
        toast.success(
            `${reservation.customer?.name ?? 'Guest'} → ${statusLabel(status)}`,
        );
    } catch (caught) {
        if (caught instanceof ApiError) {
            toast.error(errorMessage(caught));
        }
    } finally {
        updating.value = null;
    }
}

watch(
    () => [filters.value.status, filters.value.date],
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
    <Head title="Reservations · MORI Admin" />

    <AdminTopbar title="Reservations" :subtitle="restaurant.name" />

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
                        {{ option ? statusLabel(option) : 'All statuses' }}
                    </option>
                </select>
            </div>

            <div class="space-y-1.5">
                <label for="date" class="mori-field-label"> Date </label>
                <Input
                    id="date"
                    v-model="filters.date"
                    type="date"
                    class="h-9"
                />
            </div>

            <AppButton
                v-if="filters.date || filters.status"
                variant="ghost"
                size="sm"
                @click="
                    filters.date = '';
                    filters.status = '';
                "
            >
                Clear filters
            </AppButton>
        </div>

        <p
            v-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div class="mt-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                row-key="id"
                :loading="loading"
                striped
                empty-title="No reservations"
                empty-description="Bookings made on the storefront appear here."
            >
                <template #cell-start_time="{ row }">
                    <div class="whitespace-nowrap">
                        <p class="font-medium text-mori-ink">
                            {{ formatDate(String(row.start_time)) }}
                        </p>
                        <p class="text-xs text-mori-muted">
                            {{ formatTime(String(row.start_time)) }}–{{
                                formatTime(String(row.end_time))
                            }}
                        </p>
                    </div>
                </template>

                <template #cell-customer="{ row }">
                    <div v-if="row.customer">
                        <p class="text-mori-ink">{{ customer(row).name }}</p>
                        <p class="text-xs text-mori-muted">
                            {{ customer(row).email }}
                        </p>
                    </div>
                    <span v-else class="text-mori-muted">—</span>
                </template>

                <template #cell-party_size="{ row }">
                    {{ row.party_size }}
                </template>

                <template #cell-table="{ row }">
                    <span v-if="row.table" class="text-mori-ink">
                        {{ tableLabel(row) }}
                    </span>
                    <span v-else class="text-mori-muted">—</span>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge :status="String(row.status)" />
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-end gap-1">
                        <AppButton
                            v-for="next in nextStatuses(row)"
                            :key="next"
                            variant="ghost"
                            size="sm"
                            :loading="updating === row.id"
                            @click.stop="setStatus(asReservation(row), next)"
                        >
                            {{ statusLabel(next) }}
                        </AppButton>
                    </div>
                </template>
            </DataTable>
        </div>

        <div
            v-if="meta && meta.last_page > 1"
            class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm"
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
