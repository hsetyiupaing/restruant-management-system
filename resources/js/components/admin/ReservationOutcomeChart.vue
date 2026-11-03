<script setup lang="ts">
import { computed } from 'vue';
import ReportCard from '@/components/mori/ReportCard.vue';
import DonutChart from '@/components/mori/DonutChart.vue';

const props = defineProps<{
    total: number;
    completed: number;
    noShow: number;
}>();

const outcomes = computed(() =>
    [
        {
            label: 'Completed',
            value: props.completed,
            color: 'var(--mori-wine)',
        },
        {
            label: 'No-show',
            value: props.noShow,
            color: 'var(--mori-brass)',
        },
        {
            label: 'Other',
            value: Math.max(0, props.total - props.completed - props.noShow),
            color: 'var(--mori-line)',
        },
    ].filter((outcome) => outcome.value > 0),
);
</script>

<template>
    <ReportCard
        class="w-full min-w-0"
        title="Reservations"
        caption="Outcome mix for the period."
    >
        <DonutChart
            v-if="outcomes.length"
            :slices="outcomes"
            label="Reservation outcomes"
            legend-placement="below"
            :truncate-labels="false"
        />
        <p v-else class="py-10 text-center text-sm text-mori-muted">
            No reservations in this period.
        </p>
    </ReportCard>
</template>
