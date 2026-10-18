<script setup lang="ts" generic="T extends Record<string, unknown>">
import { computed } from 'vue';
import { Skeleton } from '@/components/ui/skeleton';
import EmptyState from '@/components/mori/EmptyState.vue';

export type Column = {
    key: string;
    label: string;
    align?: 'left' | 'right' | 'center';
    class?: string;
};

const props = withDefaults(
    defineProps<{
        columns: Column[];
        rows: T[];
        rowKey: keyof T | ((row: T) => string | number);
        loading?: boolean;
        emptyTitle?: string;
        emptyDescription?: string;
        striped?: boolean;
    }>(),
    {
        loading: false,
        emptyTitle: 'Nothing here yet',
        striped: false,
    },
);

const emit = defineEmits<{
    (e: 'row-click', row: T): void;
}>();

function keyFor(row: T): string | number {
    return typeof props.rowKey === 'function'
        ? props.rowKey(row)
        : (row[props.rowKey] as string | number);
}

function cell(row: T, key: string): unknown {
    return row[key];
}

const alignClass = (align?: Column['align']): string =>
    align === 'right'
        ? 'text-right'
        : align === 'center'
          ? 'text-center'
          : 'text-left';

const isEmpty = computed(() => !props.loading && props.rows.length === 0);
</script>

<template>
    <div class="overflow-x-auto rounded-lg border border-mori-line bg-mori-paper">
        <table class="w-full min-w-[640px] border-collapse text-sm">
            <thead>
                <tr class="border-b border-mori-line bg-mori-cream">
                    <th
                        v-for="column in columns"
                        :key="column.key"
                        scope="col"
                        class="px-4 py-2.5 text-[11px] font-semibold tracking-[0.1em] text-mori-muted uppercase"
                        :class="[alignClass(column.align), column.class]"
                    >
                        {{ column.label }}
                    </th>
                </tr>
            </thead>
            <tbody v-if="loading">
                <tr
                    v-for="index in 5"
                    :key="`skeleton-${index}`"
                    class="border-b border-mori-line"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-4"
                    >
                        <Skeleton class="h-4 w-full max-w-40 bg-mori-line/60" />
                    </td>
                </tr>
            </tbody>
            <tbody v-else>
                <tr
                    v-for="row in rows"
                    :key="keyFor(row)"
                    class="border-b border-mori-line transition-colors last:border-0"
                    :class="[
                        striped ? 'odd:bg-[#fbf9f4]' : '',
                        $attrs.onRowClick || $attrs.onRow_click
                            ? 'cursor-pointer hover:bg-mori-cream'
                            : '',
                    ]"
                    @click="emit('row-click', row)"
                >
                    <td
                        v-for="column in columns"
                        :key="column.key"
                        class="px-4 py-3 text-mori-ink"
                        :class="[alignClass(column.align), column.class]"
                    >
                        <slot :name="`cell-${column.key}`" :row="row">
                            {{ cell(row, column.key) }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>

        <EmptyState
            v-if="isEmpty"
            :title="emptyTitle"
            :description="emptyDescription"
            class="rounded-none border-0"
        />
    </div>
</template>
