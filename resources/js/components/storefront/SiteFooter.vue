<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import BrandLogo from '@/components/mori/BrandLogo.vue';
import type { Restaurant } from '@/types/restaurant';

defineProps<{
    restaurant: Restaurant | null;
}>();

const days = [
    ['mon', 'Monday'],
    ['tue', 'Tuesday'],
    ['wed', 'Wednesday'],
    ['thu', 'Thursday'],
    ['fri', 'Friday'],
    ['sat', 'Saturday'],
    ['sun', 'Sunday'],
] as const;
</script>

<template>
    <footer class="mt-20 border-t border-mori-line bg-mori-cream">
        <div class="mori-container grid gap-10 py-14 md:grid-cols-3">
            <div class="space-y-4">
                <BrandLogo />
                <p class="max-w-xs text-sm leading-relaxed text-mori-muted">
                    Seasonal cooking over open fire, served in a room built for
                    long evenings.
                </p>
            </div>

            <div>
                <h2
                    class="text-xs font-semibold tracking-[0.22em] text-mori-ink uppercase"
                >
                    Visit
                </h2>
                <address
                    class="mt-4 space-y-1 text-sm text-mori-muted not-italic"
                >
                    <p>
                        {{ restaurant?.address ?? 'Address to be confirmed' }}
                    </p>
                    <p v-if="restaurant?.phone">{{ restaurant.phone }}</p>
                </address>
            </div>

            <div>
                <h2
                    class="text-xs font-semibold tracking-[0.22em] text-mori-ink uppercase"
                >
                    Hours
                </h2>
                <dl class="mt-4 space-y-1 text-sm text-mori-muted">
                    <template v-if="restaurant?.opening_hours">
                        <div
                            v-for="[key, label] in days"
                            :key="key"
                            class="flex justify-between gap-4"
                        >
                            <dt>{{ label }}</dt>
                            <dd>
                                <template
                                    v-if="
                                        restaurant.opening_hours[key]?.open &&
                                        restaurant.opening_hours[key]?.close
                                    "
                                >
                                    {{ restaurant.opening_hours[key]?.open }}–{{
                                        restaurant.opening_hours[key]?.close
                                    }}
                                </template>
                                <template v-else>Closed</template>
                            </dd>
                        </div>
                    </template>
                    <p v-else>Open daily</p>
                </dl>
            </div>
        </div>

        <div class="border-t border-mori-line">
            <div
                class="mori-container flex flex-wrap items-center justify-between gap-3 py-5 text-xs text-mori-muted"
            >
                <p>
                    &copy; {{ new Date().getFullYear() }} MORI. All rights
                    reserved.
                </p>
                <nav class="flex gap-5" aria-label="Footer">
                    <Link href="/menu" class="hover:text-mori-wine">Menu</Link>
                    <Link href="/reserve" class="hover:text-mori-wine"
                        >Reservations</Link
                    >
                    <Link href="/admin" class="hover:text-mori-wine"
                        >Staff</Link
                    >
                </nav>
            </div>
        </div>
    </footer>
</template>
