<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
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

const quoteBands = [
    {
        quote: 'Fire does the talking. We just listen.',
        image: '/images/menu/grilled-chicken.jpg',
    },
    {
        quote: 'Nothing leaves the kitchen that we wouldn’t serve at our own table.',
        image: '/images/menu/green-curry.jpg',
    },
    {
        quote: 'Long evenings were always the plan.',
        image: '/images/menu/hero.png',
    },
];

const marketSteps = [
    {
        number: '01',
        title: 'Source',
        text: 'Lemongrass, galangal and Thai basil come from growers within 100 km of the kitchen.',
    },
    {
        number: '02',
        title: 'Prep',
        text: 'Pastes are pounded by hand each morning in a stone mortar. No shortcuts, no jars.',
    },
    {
        number: '03',
        title: 'Fire',
        text: 'Skewers, thighs and whole fish are cooked over charcoal and finished with fresh lime.',
    },
];

const restaurantNumbers = [
    { figure: '6:00', label: 'Market run, every morning' },
    { figure: '30', label: 'Dishes on the menu' },
    { figure: '100%', label: 'Hand-pounded curry pastes' },
    { figure: '7', label: 'Days a week, 11:00 to 22:00' },
];

function todayKey(): string {
    return days[new Date().getUTCDay() === 0 ? 6 : new Date().getUTCDay() - 1];
}

let revealObserver: IntersectionObserver | null = null;

onMounted(() => {
    if (
        !('IntersectionObserver' in window) ||
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    ) {
        return;
    }

    const cards = document.querySelectorAll<HTMLElement>('.home-scroll-reveal');

    if (cards.length === 0) {
        return;
    }

    cards.forEach((card) => card.classList.add('is-pending'));

    revealObserver = new IntersectionObserver(
        (entries, observer) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12, rootMargin: '0px 0px -32px 0px' },
    );

    cards.forEach((card) => revealObserver?.observe(card));
});

onUnmounted(() => revealObserver?.disconnect());
</script>

<template>
    <Head title="MORI · Kitchen & Table" />

    <section
        class="relative isolate flex min-h-[29rem] items-center overflow-hidden bg-mori-ink sm:min-h-[34rem] lg:min-h-[39rem]"
    >
        <img
            src="/images/menu/hero.png"
            alt=""
            fetchpriority="high"
            class="absolute inset-0 size-full object-cover"
        />
        <div class="home-hero-overlay absolute inset-0" aria-hidden="true" />

        <div
            class="mori-container relative z-10 flex w-full justify-center py-16 sm:py-20"
        >
            <div class="flex max-w-4xl flex-col items-center text-center">
                <p
                    class="home-hero-text text-xs font-semibold tracking-[0.28em] text-mori-sand uppercase"
                >
                    Now serving
                </p>
                <h1
                    class="home-hero-text mt-5 font-display text-5xl leading-tight font-semibold break-words text-white sm:text-6xl"
                >
                    {{ restaurant?.name ?? 'MORI' }}
                </h1>
                <p
                    class="home-hero-text mt-4 font-display text-2xl leading-snug text-white sm:text-3xl"
                >
                    Fire, season and a table set for you.
                </p>
                <p
                    class="home-hero-text mt-5 max-w-2xl text-base leading-relaxed text-white/85 sm:text-lg"
                >
                    We cook with the market's best each morning. Order for
                    pickup or delivery, or book a seat by the window.
                </p>
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <AppButton href="/menu" size="lg">
                        View the menu
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </AppButton>
                    <AppButton href="/reserve" variant="outline" size="lg">
                        Reserve a table
                    </AppButton>
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
                v-for="(dish, index) in featuredDishes"
                :key="dish.id"
                :dish="dish"
                class="home-scroll-reveal"
                :style="{ '--reveal-delay': `${index * 100}ms` }"
            />
        </div>
        <p v-else class="mt-8 text-sm text-mori-muted">
            Featured dishes will appear here once the kitchen marks them.
        </p>
    </section>

    <section
        v-for="(band, index) in quoteBands.slice(0, 1)"
        :key="band.quote"
        class="quote-band"
        :style="{ '--quote-band-image': `url('${band.image}')` }"
        :aria-label="`Kitchen note ${index + 1}`"
    >
        <blockquote class="quote-band__quote">
            <p>“{{ band.quote }}”</p>
        </blockquote>
    </section>

    <section class="market-section border-y border-mori-line bg-mori-cream">
        <div class="mori-container py-16 sm:py-20">
            <header class="mx-auto max-w-3xl text-center">
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-mori-wine uppercase"
                >
                    From market to fire
                </p>
                <h2
                    class="mori-heading mt-3 text-3xl leading-tight text-mori-ink sm:text-4xl"
                >
                    Every day starts at 6 a.m.
                </h2>
                <p
                    class="mt-4 text-base leading-relaxed text-mori-muted sm:text-lg"
                >
                    Before the doors open, our cooks visit the Khlong Toei
                    market for herbs, mangoes, chillies and the day’s catch.
                    What looks best goes on the menu. What doesn’t, stays at the
                    stall.
                </p>
            </header>

            <div class="market-steps mt-10 grid gap-4 md:grid-cols-3 md:gap-5">
                <article
                    v-for="(step, index) in marketSteps"
                    :key="step.number"
                    class="market-step home-scroll-reveal rounded-md border border-mori-line bg-mori-paper p-6 sm:p-7"
                    :style="{ '--reveal-delay': `${index * 130}ms` }"
                >
                    <p
                        class="font-display text-3xl text-mori-brass-ink"
                        aria-hidden="true"
                    >
                        {{ step.number }}
                    </p>
                    <h3 class="mori-heading mt-5 text-2xl text-mori-ink">
                        {{ step.title }}
                    </h3>
                    <p class="mt-3 text-sm leading-relaxed text-mori-muted">
                        {{ step.text }}
                    </p>
                </article>
            </div>

            <dl
                class="mt-12 grid grid-cols-2 border-y border-mori-field/40 sm:grid-cols-4"
            >
                <div
                    v-for="(stat, index) in restaurantNumbers"
                    :key="stat.figure"
                    class="px-3 py-5 text-center sm:px-5 sm:py-6"
                    :class="[
                        index % 2 === 1 ? 'border-l border-mori-field/40' : '',
                        index > 1
                            ? 'border-t border-mori-field/40 sm:border-t-0'
                            : '',
                        index > 0 ? 'sm:border-l sm:border-mori-field/40' : '',
                    ]"
                >
                    <dd
                        class="font-display text-3xl text-mori-wine sm:text-4xl"
                    >
                        {{ stat.figure }}
                    </dd>
                    <dt
                        class="mt-2 text-xs leading-relaxed text-mori-muted sm:text-sm"
                    >
                        {{ stat.label }}
                    </dt>
                </div>
            </dl>
        </div>
    </section>

    <section
        v-for="(band, index) in quoteBands.slice(1, 2)"
        :key="band.quote"
        class="quote-band"
        :style="{ '--quote-band-image': `url('${band.image}')` }"
        :aria-label="`Kitchen note ${index + 2}`"
    >
        <blockquote class="quote-band__quote">
            <p>“{{ band.quote }}”</p>
        </blockquote>
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

    <section
        v-for="(band, index) in quoteBands.slice(2)"
        :key="band.quote"
        class="quote-band"
        :style="{ '--quote-band-image': `url('${band.image}')` }"
        :aria-label="`Kitchen note ${index + 3}`"
    >
        <blockquote class="quote-band__quote">
            <p>“{{ band.quote }}”</p>
        </blockquote>
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
