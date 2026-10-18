<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        label: string;
        value: string;
        hint?: string;
        icon?: Component;
        /** Wine instead of brass for the tile's top rule. */
        primary?: boolean;
    }>(),
    { primary: false },
);
</script>

<template>
    <div class="kpi-tile p-4" :class="{ 'is-primary': primary }">
        <div class="flex items-start justify-between gap-2">
            <p class="kpi-label">{{ label }}</p>
            <component
                :is="icon"
                v-if="icon"
                class="size-4 shrink-0 text-mori-brass-ink"
                aria-hidden="true"
            />
        </div>
        <p class="kpi-value mt-2">{{ value }}</p>
        <p v-if="hint" class="kpi-hint mt-1">{{ hint }}</p>
    </div>
</template>
