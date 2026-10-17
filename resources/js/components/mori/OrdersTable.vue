<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DataTable from '@/components/mori/DataTable.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import type { Column } from '@/components/mori/DataTable.vue';
import { formatDateTime, formatMoney, statusLabel } from '@/lib/format';
import type { Order } from '@/types/restaurant';

const props = withDefaults(
    defineProps<{
        orders: Order[];
        loading?: boolean;
        compact?: boolean;
        selectable?: boolean;
        selectedId?: number | null;
    }>(),
    {
        loading: false,
        compact: false,
        selectable: false,
        selectedId: null,
    },
);

const emit = defineEmits<{
    (e: 'select', order: Order): void;
}>();

const columns = computed<Column[]>(() => {
    const base: Column[] = [
        { key: 'order_number', label: 'Order' },
        { key: 'order_type', label: 'Type' },
        { key: 'status', label: 'Status' },
        { key: 'payment_status', label: 'Payment' },
        { key: 'total', label: 'Total', align: 'right' },
        { key: 'created_at', label: 'Placed', align: 'right' },
    ];

    if (props.compact) {
        return base.filter((column) =>
            ['order_number', 'status', 'total', 'created_at'].includes(column.key),
        );
    }

    return base;
});

// DataTable is generic over a row shape; orders are structurally compatible
// once widened, and the cell slots below render the typed fields.
const rows = computed(() => props.orders as unknown as Record<string, unknown>[]);

function selectRow(row: Record<string, unknown>): void {
    emit('select', row as unknown as Order);
}

function customerName(row: Record<string, unknown>): string | null {
    const customer = row.customer as { name?: string } | null | undefined;

    return customer?.name ?? null;
}

function orderTotal(row: Record<string, unknown>): string {
    return (row.totals as Order['totals'] | undefined)?.total ?? '0';
}
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rows"
        row-key="id"
        :loading="loading"
        striped
        empty-title="No orders yet"
        empty-description="Orders placed by customers will appear here."
        @row-click="selectRow"
    >
        <template #cell-order_number="{ row }">
            <div class="flex flex-col gap-0.5">
                <Link
                    :href="`/admin/orders/${row.id}`"
                    class="font-medium text-mori-ink underline-offset-4 hover:underline"
                    @click.stop
                >
                    {{ row.order_number }}
                </Link>
                <span
                    v-if="customerName(row)"
                    class="text-xs text-mori-muted"
                >
                    {{ customerName(row) }}
                </span>
            </div>
        </template>

        <template #cell-order_type="{ row }">
            <span class="text-xs tracking-wide text-mori-muted uppercase">
                {{ statusLabel(String(row.order_type)) }}
            </span>
        </template>

        <template #cell-status="{ row }">
            <StatusBadge :status="String(row.status)" />
        </template>

        <template #cell-payment_status="{ row }">
            <StatusBadge :status="String(row.payment_status)" />
        </template>

        <template #cell-total="{ row }">
            {{ formatMoney(orderTotal(row)) }}
        </template>

        <template #cell-created_at="{ row }">
            <span class="text-mori-muted">
                {{ formatDateTime(String(row.created_at)) }}
            </span>
        </template>
    </DataTable>
</template>
