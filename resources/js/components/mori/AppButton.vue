<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

type Variant = 'primary' | 'outline' | 'ghost' | 'dark' | 'light';
type Size = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
        size?: Size;
        href?: string;
        method?: 'get' | 'post' | 'put' | 'patch' | 'delete';
        type?: 'button' | 'submit';
        disabled?: boolean;
        loading?: boolean;
        block?: boolean;
        icon?: Component;
        class?: string;
    }>(),
    {
        variant: 'primary',
        size: 'md',
        type: 'button',
        disabled: false,
        loading: false,
        block: false,
    },
);

const variants: Record<Variant, string> = {
    primary:
        'bg-mori-wine text-white hover:bg-mori-wine-hover focus-visible:outline-mori-wine',
    outline:
        'border border-mori-line bg-transparent text-mori-ink hover:bg-mori-cream focus-visible:outline-mori-ink',
    ghost:
        'bg-transparent text-mori-ink hover:bg-mori-cream focus-visible:outline-mori-ink',
    dark: 'bg-mori-ink text-mori-paper hover:bg-mori-ink/90 focus-visible:outline-mori-ink',
    light: 'bg-mori-cream text-mori-ink hover:bg-mori-sand/60 focus-visible:outline-mori-ink',
};

const sizes: Record<Size, string> = {
    sm: 'h-9 px-3 text-sm',
    md: 'h-11 px-5 text-sm',
    lg: 'h-12 px-6 text-base',
};

const classes = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded font-medium transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2',
    'disabled:pointer-events-none disabled:opacity-50',
    variants[props.variant],
    sizes[props.size],
    props.block ? 'w-full' : '',
    props.class ?? '',
]);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :method="method ?? 'get'"
        :as="method && method !== 'get' ? 'button' : 'a'"
        :class="cn(classes)"
    >
        <component :is="icon" v-if="icon" class="size-4" aria-hidden="true" />
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="disabled || loading"
        :class="cn(classes)"
    >
        <span
            v-if="loading"
            class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent"
            aria-hidden="true"
        />
        <component
            v-else-if="icon"
            :is="icon"
            class="size-4"
            aria-hidden="true"
        />
        <slot />
    </button>
</template>
