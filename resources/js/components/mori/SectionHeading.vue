<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    text?: string;
}>();
</script>

<template>
    <div class="flex min-w-0 flex-wrap items-end justify-between gap-4">
        <div class="min-w-0 max-w-2xl">
            <p
                v-if="eyebrow"
                class="text-xs font-medium tracking-[0.24em] text-mori-wine uppercase"
            >
                {{ eyebrow }}
            </p>
            <h2 class="mori-heading mt-2 text-2xl text-mori-ink break-words sm:text-3xl lg:text-4xl">
                {{ title }}
            </h2>
            <p v-if="text" class="mt-3 text-sm text-mori-muted break-words sm:text-base">
                {{ text }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
