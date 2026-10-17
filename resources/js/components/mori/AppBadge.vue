<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type Tone =
    | 'neutral'
    | 'wine'
    | 'sage'
    | 'sand'
    | 'amber'
    | 'blue'
    | 'green'
    | 'red';

const props = withDefaults(
    defineProps<{
        tone?: Tone;
        class?: string;
    }>(),
    { tone: 'neutral' },
);

const tones: Record<Tone, string> = {
    neutral: 'bg-mori-cream text-mori-ink',
    wine: 'bg-mori-wine/10 text-mori-wine',
    sage: 'bg-mori-sage/10 text-mori-sage',
    sand: 'bg-mori-sand/40 text-mori-ink',
    amber: 'bg-amber-100 text-amber-900',
    blue: 'bg-sky-100 text-sky-900',
    green: 'bg-emerald-100 text-emerald-900',
    red: 'bg-red-100 text-red-800',
};

const classes = computed(() => [
    'inline-flex items-center gap-1 rounded-sm px-2 py-0.5 text-xs font-medium',
    tones[props.tone],
    props.class ?? '',
]);
</script>

<template>
    <span :class="cn(classes)"><slot /></span>
</template>
