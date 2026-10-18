<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/mori/BrandLogo.vue';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="mori-shell flex min-h-svh flex-col items-center justify-center bg-mori-cream px-5 py-10 sm:px-6 md:py-14"
    >
        <div class="w-full" :class="'max-w-md'">
            <div class="flex flex-col gap-7">
                <div class="flex flex-col items-center gap-4 text-center">
                    <Link
                        href="/"
                        class="shrink-0 focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mori-wine"
                        aria-label="MORI home"
                    >
                        <BrandLogo />
                    </Link>
                    <div class="space-y-2">
                        <h1 class="mori-heading text-2xl text-mori-ink">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="mx-auto max-w-sm text-sm leading-relaxed text-mori-muted"
                        >
                            {{ description }}
                        </p>
                    </div>
                </div>

                <div
                    class="rounded border border-mori-line bg-mori-paper p-6 shadow-[0_1px_0_rgba(34,32,29,0.04)] sm:p-7"
                >
                    <slot />
                </div>

                <p class="text-center text-xs text-mori-muted">
                    <Link
                        href="/menu"
                        class="underline underline-offset-4 hover:text-mori-wine focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                    >
                        Browse the menu
                    </Link>
                    without an account.
                </p>
            </div>
        </div>
    </div>
</template>
