<script setup lang="ts">
import { computed } from 'vue';

export type DonutSlice = {
    label: string;
    value: number;
    /** Any CSS colour — callers pass the palette tokens. */
    color: string;
};

const props = withDefaults(
    defineProps<{
        slices: DonutSlice[];
        label: string;
        size?: number;
        thickness?: number;
    }>(),
    { size: 140, thickness: 18 },
);

const total = computed(() =>
    props.slices.reduce((sum, slice) => sum + slice.value, 0),
);

const radius = computed(() => (props.size - props.thickness) / 2);

/** SVG stroke-dasharray segments, laid out clockwise from 12 o'clock. */
const segments = computed(() => {
    if (total.value === 0) {
        return [];
    }

    const circumference = 2 * Math.PI * radius.value;
    let offset = 0;

    return props.slices.map((slice) => {
        const length = (slice.value / total.value) * circumference;
        const segment = {
            ...slice,
            dasharray: `${length} ${circumference - length}`,
            dashoffset: -offset,
        };

        offset += length;

        return segment;
    });
});

function share(value: number): number {
    return total.value === 0 ? 0 : Math.round((value / total.value) * 100);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-5">
        <div
            class="relative shrink-0"
            :style="{ width: `${size}px`, height: `${size}px` }"
        >
            <svg
                :viewBox="`0 0 ${size} ${size}`"
                role="img"
                :aria-label="`${label}: ${slices.map((slice) => `${slice.label} ${share(slice.value)}%`).join(', ')}`"
                class="size-full -rotate-90"
            >
                <circle
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="radius"
                    fill="none"
                    stroke="var(--mori-line)"
                    :stroke-width="thickness"
                />
                <circle
                    v-for="segment in segments"
                    :key="segment.label"
                    :cx="size / 2"
                    :cy="size / 2"
                    :r="radius"
                    fill="none"
                    :stroke="segment.color"
                    :stroke-width="thickness"
                    :stroke-dasharray="segment.dasharray"
                    :stroke-dashoffset="segment.dashoffset"
                >
                    <title>
                        {{ segment.label }} · {{ share(segment.value) }}%
                    </title>
                </circle>
            </svg>
        </div>

        <dl class="min-w-0 flex-1 space-y-2 text-sm">
            <div
                v-for="slice in slices"
                :key="slice.label"
                class="flex items-center justify-between gap-3"
            >
                <dt class="flex min-w-0 items-center gap-2 text-mori-muted">
                    <span
                        class="size-2.5 shrink-0 rounded-sm"
                        :style="{ backgroundColor: slice.color }"
                        aria-hidden="true"
                    />
                    <span class="truncate">{{ slice.label }}</span>
                </dt>
                <dd class="font-medium whitespace-nowrap text-mori-ink">
                    {{ slice.value }} ({{ share(slice.value) }}%)
                </dd>
            </div>
        </dl>
    </div>
</template>
