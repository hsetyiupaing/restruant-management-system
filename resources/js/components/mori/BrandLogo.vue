<script setup lang="ts">
withDefaults(
    defineProps<{
        inverse?: boolean;
        compact?: boolean;
    }>(),
    { inverse: false, compact: false },
);
</script>

<template>
    <span class="inline-flex max-w-full items-baseline gap-1.5 sm:gap-2">
        <span
            class="shrink-0 font-display text-xl font-semibold tracking-[0.14em] sm:text-2xl sm:tracking-[0.18em]"
            :class="inverse ? 'text-white' : 'text-mori-ink'"
        >
            MORI
        </span>
        <span
            v-if="!compact"
            class="hidden text-[9px] tracking-[0.2em] uppercase truncate min-[400px]:inline sm:text-[10px] sm:tracking-[0.32em]"
            :class="inverse ? 'text-white/60' : 'text-mori-muted'"
        >
            Kitchen &amp; Table
        </span>
    </span>
</template>
