<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { computed, ref } from 'vue';
import { formatDate } from '@/lib/format';

/**
 * A minimal column chart in the report style: pale-stone gridlines, warm-grey
 * axis labels, burgundy columns. Every bar is a real element with an aria-label
 * so the figures are readable without hovering.
 */
const props = withDefaults(
    defineProps<{
        points: { date: string; value: number }[];
        /** Formats the value shown in the tooltip and the aria-label. */
        format: (value: number) => string;
        /** Accessible summary of what the chart shows. */
        label: string;
        height?: number;
        /** Formats dates on the x-axis without changing tooltip dates. */
        formatDateLabel?: (date: string) => string;
        /** Upper bound for visible x-axis labels. */
        maxDateLabels?: number;
    }>(),
    { height: 180, maxDateLabels: 6 },
);

/** Four steps above zero — enough to read a shape, few enough to stay quiet. */
const TICKS = 4;
const chartArea = ref<HTMLElement | null>(null);
const chartWidth = ref(0);

useResizeObserver(chartArea, (entries) => {
    chartWidth.value = entries[0]?.contentRect.width ?? 0;
});

const max = computed(() => Math.max(1, ...props.points.map((p) => p.value)));

const ticks = computed(() =>
    Array.from({ length: TICKS + 1 }, (_, index) => {
        const value = (max.value / TICKS) * index;

        return {
            value,
            // Position from the bottom of the plot area.
            bottom: `${(index / TICKS) * 100}%`,
        };
    }).reverse(),
);

/** Adjust tick density to the available width while preserving both endpoints. */
const labelEvery = computed(() => {
    if (props.points.length < 2) {
        return 1;
    }

    const labelsByWidth =
        chartWidth.value > 0
            ? Math.max(2, Math.floor(chartWidth.value / 64))
            : 6;
    const labelCount = Math.min(props.maxDateLabels, labelsByWidth);

    return Math.max(1, Math.ceil((props.points.length - 1) / (labelCount - 1)));
});
</script>

<template>
    <div class="mt-4 flex gap-2">
        <!-- value axis -->
        <div
            class="relative shrink-0"
            :style="{ height: `${height}px`, width: '3.5rem' }"
            aria-hidden="true"
        >
            <span
                v-for="tick in ticks"
                :key="tick.value"
                class="chart-axis-label absolute right-0 -translate-y-1/2"
                :style="{ bottom: tick.bottom }"
            >
                {{ format(tick.value) }}
            </span>
        </div>

        <div ref="chartArea" class="min-w-0 flex-1">
            <div class="relative" :style="{ height: `${height}px` }">
                <!-- gridlines sit behind the columns -->
                <div class="absolute inset-0" aria-hidden="true">
                    <div
                        v-for="tick in ticks"
                        :key="`grid-${tick.value}`"
                        class="chart-gridline absolute inset-x-0"
                        :style="{ bottom: tick.bottom }"
                    />
                </div>

                <div
                    class="absolute inset-0 flex items-end gap-[2px]"
                    role="img"
                    :aria-label="label"
                >
                    <div
                        v-for="point in points"
                        :key="point.date"
                        class="group relative flex h-full flex-1 items-end"
                    >
                        <div
                            class="w-full rounded-t-sm bg-mori-wine transition-colors group-hover:bg-mori-wine-hover"
                            :style="{
                                height: `${Math.max(1, (point.value / max) * 100)}%`,
                            }"
                            :title="`${formatDate(point.date)} · ${format(point.value)}`"
                        />
                    </div>
                </div>
            </div>

            <!-- date axis -->
            <div class="mt-1 flex gap-[2px]" aria-hidden="true">
                <div
                    v-for="(point, index) in points"
                    :key="`label-${point.date}`"
                    class="min-w-0 flex-1 text-center"
                >
                    <span
                        v-if="
                            index % labelEvery === 0 ||
                            index === points.length - 1
                        "
                        class="chart-axis-label whitespace-nowrap"
                    >
                        {{
                            formatDateLabel?.(point.date) ??
                            formatDate(point.date)
                        }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
