<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    text?: string;
}>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
            <p
                v-if="eyebrow"
                class="text-xs font-medium tracking-[0.24em] text-mori-wine uppercase"
            >
                {{ eyebrow }}
            </p>
            <h2 class="mori-heading mt-2 text-3xl text-mori-ink sm:text-4xl">
                {{ title }}
            </h2>
            <p v-if="text" class="mt-3 text-sm text-mori-muted sm:text-base">
                {{ text }}
            </p>
        </div>
        <div v-if="$slots.action">
            <slot name="action" />
        </div>
    </div>
</template>
