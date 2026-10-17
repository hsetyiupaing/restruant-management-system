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
    <span class="inline-flex items-baseline gap-2">
        <span
            class="font-display text-2xl font-semibold tracking-[0.18em]"
            :class="inverse ? 'text-white' : 'text-mori-ink'"
        >
            MORI
        </span>
        <span
            v-if="!compact"
            class="text-[10px] tracking-[0.32em] uppercase"
            :class="inverse ? 'text-white/60' : 'text-mori-muted'"
        >
            Kitchen &amp; Table
        </span>
    </span>
</template>
