<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import FoodCard from '@/components/mori/FoodCard.vue';
import SectionHeading from '@/components/mori/SectionHeading.vue';
import AppButton from '@/components/mori/AppButton.vue';
import type { MenuItem, Restaurant } from '@/types/restaurant';

const props = defineProps<{
    restaurant: Restaurant | null;
    featuredDishes: MenuItem[];
    openingHours: Record<
        string,
        { open?: string; close?: string } | null
    > | null;
    totalDishes: number;
}>();

const days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as const;
const labels: Record<string, string> = {
    mon: 'Mon',
    tue: 'Tue',
    wed: 'Wed',
    thu: 'Thu',
    fri: 'Fri',
    sat: 'Sat',
    sun: 'Sun',
};

function todayKey(): string {
    return days[new Date().getUTCDay() === 0 ? 6 : new Date().getUTCDay() - 1];
}
</script>

<template>
    <Head title="MORI · Kitchen & Table" />

    <section class="relative overflow-hidden bg-mori-cream">
        <div
            class="mori-container grid items-center gap-10 py-16 lg:grid-cols-2 lg:py-24"
        >
            <div>
                <p class="text-xs tracking-[0.28em] text-mori-wine uppercase">
                    Now serving
                </p>
                <h1
                    class="mori-heading mt-4 text-4xl leading-[1.05] sm:text-5xl lg:text-6xl"
                >
                    Fire, season and a table set for you.
                </h1>
                <p
                    class="mt-5 max-w-md text-base leading-relaxed text-mori-muted"
                >
                    {{ restaurant?.name ?? 'MORI' }} cooks with the market's
                    best each morning. Order for pickup or delivery, or book a
                    seat by the window.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <AppButton href="/menu" size="lg">
                        View the menu
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </AppButton>
                    <AppButton href="/reserve" variant="outline" size="lg">
                        Reserve a table
                    </AppButton>
                </div>
            </div>

            <div class="relative">
                <div
                    class="aspect-[4/5] overflow-hidden rounded bg-mori-sand/40"
                >
                    <img
                        v-if="featuredDishes[0]?.image_url"
                        :src="featuredDishes[0].image_url"
                        :alt="featuredDishes[0].name"
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center bg-gradient-to-br from-mori-sand/60 to-mori-cream"
                    >
                        <span class="font-display text-6xl text-mori-wine/40"
                            >M</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="mori-container py-16">
        <SectionHeading
            eyebrow="From the kitchen"
            title="Dishes we are proud of"
            text="A handful of plates that define the room. The full menu has more."
        >
            <template #action>
                <AppButton href="/menu" variant="ghost" size="sm">
                    All {{ totalDishes }} dishes
                    <ArrowRight class="size-4" aria-hidden="true" />
                </AppButton>
            </template>
        </SectionHeading>

        <div
            v-if="featuredDishes.length"
            class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
        >
            <FoodCard
                v-for="dish in featuredDishes"
                :key="dish.id"
                :dish="dish"
            />
        </div>
        <p v-else class="mt-8 text-sm text-mori-muted">
            Featured dishes will appear here once the kitchen marks them.
        </p>
    </section>

    <section class="border-y border-mori-line bg-mori-cream/50">
        <div class="mori-container grid gap-10 py-16 md:grid-cols-2">
            <div>
                <SectionHeading
                    eyebrow="Our story"
                    title="A room built around the fire"
                />
                <p
                    class="mt-4 max-w-prose text-sm leading-relaxed text-mori-muted"
                >
                    We keep the menu short, the sourcing local and the cooking
                    unhurried. Come for a quick lunch at the counter or settle
                    in for a long dinner — the kitchen works the same either
                    way.
                </p>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <h3
                        class="text-xs font-semibold tracking-[0.2em] text-mori-ink uppercase"
                    >
                        Find us
                    </h3>
                    <address
                        class="mt-3 space-y-1 text-sm text-mori-muted not-italic"
                    >
                        <p>
                            {{
                                restaurant?.address ?? 'Address to be confirmed'
                            }}
                        </p>
                        <p v-if="restaurant?.phone">{{ restaurant.phone }}</p>
                    </address>
                </div>
                <div>
                    <h3
                        class="text-xs font-semibold tracking-[0.2em] text-mori-ink uppercase"
                    >
                        Opening hours
                    </h3>
                    <dl class="mt-3 space-y-1 text-sm text-mori-muted">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="flex justify-between gap-3"
                            :class="{
                                'font-medium text-mori-ink': day === todayKey(),
                            }"
                        >
                            <dt>{{ labels[day] }}</dt>
                            <dd>
                                <template
                                    v-if="
                                        openingHours?.[day]?.open &&
                                        openingHours?.[day]?.close
                                    "
                                >
                                    {{ openingHours[day]?.open }}–{{
                                        openingHours[day]?.close
                                    }}
                                </template>
                                <template v-else>Closed</template>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </section>

    <section class="mori-container py-16">
        <div
            class="flex flex-col items-start justify-between gap-6 rounded border border-mori-line bg-mori-paper p-8 md:flex-row md:items-center"
        >
            <div>
                <h2 class="mori-heading text-2xl">Planning ahead?</h2>
                <p class="mt-2 text-sm text-mori-muted">
                    Tables are released up to 30 days out. Pick a time and
                    choose your spot on the floor plan.
                </p>
            </div>
            <AppButton href="/reserve" size="lg">
                Book a table
                <ArrowRight class="size-4" aria-hidden="true" />
            </AppButton>
        </div>
    </section>

    <template v-if="!props.restaurant">
        <div class="mori-container pb-10">
            <p class="text-sm text-mori-muted">
                No restaurant is configured yet.
                <Link href="/admin" class="underline"
                    >Open the back office</Link
                >
                to activate one.
            </p>
        </div>
    </template>
</template>
