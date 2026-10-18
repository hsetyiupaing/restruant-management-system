<script setup lang="ts">
import type { Component } from 'vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded border border-dashed border-mori-field bg-mori-paper px-6 py-14 text-center"
    >
        <component
            :is="icon"
            v-if="icon"
            class="mb-3 size-8 text-mori-muted"
            aria-hidden="true"
        />
        <p class="mori-heading text-lg text-mori-ink">{{ title }}</p>
        <p v-if="description" class="mt-2 max-w-md text-sm text-mori-muted">
            {{ description }}
        </p>
        <div v-if="$slots.default" class="mt-5">
            <slot />
        </div>
    </div>
</template>
