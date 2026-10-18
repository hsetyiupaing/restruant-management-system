<script setup lang="ts">
/**
 * A report panel: the white card with a pale-stone rule and a titled header
 * used across the back office, so every panel shares one frame.
 */
withDefaults(
    defineProps<{
        title?: string;
        caption?: string;
    }>(),
    {},
);
</script>

<template>
    <section class="report-card">
        <header
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-2 border-b border-mori-line px-4 py-3"
        >
            <div>
                <h2 class="report-card-title">{{ title }}</h2>
                <p v-if="caption" class="report-caption mt-0.5">
                    {{ caption }}
                </p>
            </div>
            <slot name="actions" />
        </header>

        <div :class="title || $slots.actions ? 'p-4' : ''">
            <slot />
        </div>
    </section>
</template>
