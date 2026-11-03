<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Toaster } from '@/components/ui/sonner';
import SiteFooter from '@/components/storefront/SiteFooter.vue';
import SiteHeader from '@/components/storefront/SiteHeader.vue';
import type { Restaurant } from '@/types/restaurant';

const page = usePage();
const restaurant = computed(
    () => (page.props.restaurant as Restaurant | undefined) ?? null,
);
</script>

<template>
    <div class="mori-shell flex min-h-screen w-full max-w-full flex-col overflow-x-hidden">
        <SiteHeader />
        <div class="h-16 shrink-0 md:h-[76px]" aria-hidden="true" />
        <main class="min-w-0 w-full max-w-full flex-1">
            <slot />
        </main>
        <SiteFooter :restaurant="restaurant" />
        <Toaster position="bottom-right" />
    </div>
</template>
