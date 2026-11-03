<script setup lang="ts">
import { computed } from 'vue';
import ReportCard from '@/components/mori/ReportCard.vue';
import ColumnChart from '@/components/mori/ColumnChart.vue';
import type { Kpis } from '@/types/restaurant';

const props = defineProps<{
    points: Kpis['order_series'];
}>();

const chartPoints = computed(() =>
    props.points.map((point) => ({ date: point.date, value: point.count })),
);

const shortDateFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    timeZone: 'UTC',
});

function formatShortDate(value: string): string {
    return shortDateFormatter.format(new Date(`${value}T00:00:00Z`));
}
</script>

<template>
    <ReportCard
        class="w-full min-w-0"
        title="Order volume"
        caption="Orders placed per day."
    >
        <ColumnChart
            v-if="points.length"
            :points="chartPoints"
            :format="(value) => String(Math.round(value))"
            :format-date-label="formatShortDate"
            :max-date-labels="6"
            :label="`Daily order count for ${points.length} days`"
        />
        <p v-else class="py-10 text-center text-sm text-mori-muted">
            No orders in this period.
        </p>
    </ReportCard>
</template>
