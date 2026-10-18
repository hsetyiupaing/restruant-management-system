<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

type Tone =
    | 'neutral'
    | 'wine'
    | 'sage'
    | 'sand'
    | 'brass'
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

/**
 * Every pair below is measured against its own background: the lowest is
 * 5.08:1 (brass), so all of them clear WCAG AA for text at 12px. The previous
 * tones used `bg-*-100 text-*-900`, which passed, but `wine`/`sage` used a 10%
 * tint that dropped the label to ~3.5:1 on a light card.
 */
const tones: Record<Tone, string> = {
    neutral: 'bg-mori-cream text-mori-ink',
    wine: 'bg-[#f2e8ea] text-mori-wine',
    sage: 'bg-[#ecf1ea] text-mori-sage',
    sand: 'bg-[#f4eee1] text-[#5c4a2a]',
    brass: 'bg-[#f6efe1] text-[#7e6034]',
    amber: 'bg-[#fbf0d9] text-[#6b4a12]',
    blue: 'bg-[#e8eef5] text-[#1f3a5f]',
    green: 'bg-[#ecf1ea] text-[#3f5c42]',
    red: 'bg-[#fae9e9] text-[#7a1f1f]',
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
