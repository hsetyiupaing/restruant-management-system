<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Check, Minus, Plus, TriangleAlert } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppBadge from '@/components/mori/AppBadge.vue';
import AppButton from '@/components/mori/AppButton.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import { Textarea } from '@/components/ui/textarea';
import { useCart } from '@/composables/useCart';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import type {
    FoodRestrictionEntry,
    MenuItem,
    Restaurant,
} from '@/types/restaurant';

const props = defineProps<{
    restaurant: Restaurant | null;
    /** The `{dish}` route parameter: a slug, or a numeric id. */
    dish: string;
}>();

const page = usePage();
const { add } = useCart();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const savedRestrictions = computed<FoodRestrictionEntry[]>(
    () => page.props.auth?.foodRestrictions ?? [],
);

const item = ref<MenuItem | null>(null);
const loading = ref(true);
const notFound = ref(false);
const error = ref<string | null>(null);

const quantity = ref(1);
const note = ref('');
const adding = ref(false);
const added = ref(false);
const selectedOptions = ref<Record<string, string[]>>({});

const customizationGroups = computed(
    () => item.value?.customization_groups ?? [],
);

const unitPrice = computed(() => {
    const extraPrice = customizationGroups.value.reduce((total, group) => {
        const selectedIds = selectedOptions.value[group.id] ?? [];

        return (
            total +
            group.options.reduce(
                (groupTotal, option) =>
                    selectedIds.includes(option.id)
                        ? groupTotal + (Number(option.price) || 0)
                        : groupTotal,
                0,
            )
        );
    }, 0);

    return Number.parseFloat(item.value?.price ?? '0') + extraPrice;
});

const selectedCustomizations = computed(() =>
    customizationGroups.value.flatMap((group) => {
        const optionIds = selectedOptions.value[group.id] ?? [];
        const options = group.options
            .filter((option) => optionIds.includes(option.id))
            .map((option) => ({
                id: option.id,
                name: option.name,
                price: Number(option.price).toFixed(2),
            }));

        return options.length > 0
            ? [{ groupId: group.id, groupName: group.name, options }]
            : [];
    }),
);

const courseLabels: Record<string, string> = {
    STARTER: 'Starter',
    MAIN: 'Main',
    DESSERT: 'Dessert',
};

const dietaryTags = computed(
    () => item.value?.dietary_tags?.filter((tag) => tag.slug !== '') ?? [],
);

/**
 * Null is distinct from an empty list: null means nobody has recorded the
 * allergens, so the page must say so instead of reassuring the customer.
 */
const allergenState = computed(() => {
    const allergens = item.value?.allergens;

    if (allergens === null || allergens === undefined) {
        return 'unrecorded' as const;
    }

    return allergens.length > 0 ? ('listed' as const) : ('none' as const);
});

const lineTotal = computed(() => unitPrice.value * quantity.value);

/**
 * A restriction is compared only when it has something verifiable to compare
 * with: an allergen group recorded for the dish, or a dietary classification
 * the dish carries. "Other" free-text entries are deliberately not matched —
 * the kitchen cannot verify them, and pretending otherwise would be unsafe.
 *
 * A match is a warning, not a verdict: `allergenState === 'unrecorded'` means
 * the dish has no ingredients on file, so nothing can be ruled out.
 */
const restrictionMatches = computed(() => {
    const entry = item.value;

    if (!entry) {
        return [];
    }

    const allergenValues = new Set(
        (entry.allergens ?? []).map((allergen) => allergen.value),
    );
    const dietarySlugs = new Set(
        (entry.dietary_tags ?? []).map((tag) => tag.slug),
    );

    return savedRestrictions.value
        .map((restriction) => {
            if (restriction.allergen) {
                return allergenValues.has(restriction.allergen)
                    ? { ...restriction, reason: 'allergen' as const }
                    : null;
            }

            if (restriction.dietary_tag) {
                // Preferences are informational: a vegetarian looking at a
                // dish that is not tagged vegetarian is worth flagging.
                return {
                    ...restriction,
                    reason: dietarySlugs.has(restriction.dietary_tag)
                        ? ('matches-diet' as const)
                        : ('not-tagged' as const),
                };
            }

            return null;
        })
        .filter(
            (
                entry,
            ): entry is FoodRestrictionEntry & {
                reason: 'allergen' | 'matches-diet' | 'not-tagged';
            } => entry !== null,
        );
});

/** Allergens that match a saved allergy, used to mark the allergen chips. */
const matchedAllergens = computed(
    () =>
        new Set(
            restrictionMatches.value
                .filter((match) => match.reason === 'allergen')
                .map((match) => match.allergen),
        ),
);

const hasConfirmedMatch = computed(() =>
    restrictionMatches.value.some((match) => match.reason === 'allergen'),
);

const preferencesNotMet = computed(() =>
    restrictionMatches.value.filter((match) => match.reason === 'not-tagged'),
);

const isSoldOut = computed(
    () => item.value !== null && !item.value.is_available,
);

async function load(): Promise<void> {
    if (!props.restaurant) {
        loading.value = false;
        notFound.value = true;

        return;
    }

    loading.value = true;
    notFound.value = false;
    error.value = null;

    try {
        const response = await api.get<{ data: MenuItem }>(
            `/restaurants/${props.restaurant.slug}/menu-items/${encodeURIComponent(props.dish)}`,
            { redirectOnUnauthorized: false },
        );

        item.value = response.data;
        quantity.value = 1;
        selectedOptions.value = {};
    } catch (caught) {
        if (caught instanceof ApiError && caught.status === 404) {
            notFound.value = true;
        } else {
            error.value = errorMessage(caught);
        }
    } finally {
        loading.value = false;
    }
}

function changeQuantity(delta: number): void {
    quantity.value = Math.min(Math.max(quantity.value + delta, 1), 99);
}

function toggleOption(groupId: string, optionId: string, radio: boolean): void {
    const selected = selectedOptions.value[groupId] ?? [];

    selectedOptions.value = {
        ...selectedOptions.value,
        [groupId]: radio
            ? [optionId]
            : selected.includes(optionId)
              ? selected.filter((selectedId) => selectedId !== optionId)
              : [...selected, optionId],
    };
}

function addToOrder(): void {
    if (item.value === null || isSoldOut.value) {
        return;
    }

    adding.value = true;
    add(
        item.value,
        quantity.value,
        note.value.trim(),
        selectedCustomizations.value,
    );
    adding.value = false;
    added.value = true;

    toast.success(
        `${quantity.value} × ${item.value.name} added to your order.`,
        {
            action: {
                label: 'View order',
                onClick: () => router.visit('/checkout'),
            },
        },
    );

    window.setTimeout(() => {
        added.value = false;
    }, 2000);
}

watch(() => props.dish, load);

onMounted(load);
</script>

<template>
    <Head :title="item ? `${item.name} · MORI` : 'Dish · MORI'" />

    <div class="mori-container py-10 sm:py-12">
        <Link
            href="/menu"
            class="inline-flex items-center gap-1.5 text-sm text-mori-muted transition-colors hover:text-mori-wine focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            Back to the menu
        </Link>

        <!-- Loading -->
        <div
            v-if="loading"
            class="mt-6 grid gap-8 lg:grid-cols-2"
            aria-busy="true"
            aria-live="polite"
        >
            <div
                class="aspect-[4/3] animate-pulse rounded border border-mori-line bg-mori-cream"
            />
            <div class="space-y-4">
                <div class="h-8 w-2/3 animate-pulse rounded bg-mori-cream" />
                <div class="h-4 w-full animate-pulse rounded bg-mori-cream" />
                <div class="h-4 w-5/6 animate-pulse rounded bg-mori-cream" />
                <div class="h-4 w-1/2 animate-pulse rounded bg-mori-cream" />
            </div>
        </div>

        <!-- Not found -->
        <EmptyState
            v-else-if="notFound"
            class="mt-8"
            title="We can't find that dish"
            description="It may have left the menu or the link is out of date."
        >
            <AppButton href="/menu" size="sm">Browse the menu</AppButton>
        </EmptyState>

        <!-- Error -->
        <div
            v-else-if="error"
            class="mt-8 rounded border border-[#f0c4c4] bg-[#fae9e9] px-5 py-4 text-sm text-[#7a1f1f]"
            role="alert"
        >
            <p>{{ error }}</p>
            <button
                type="button"
                class="mt-3 font-medium underline focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2"
                @click="load"
            >
                Try again
            </button>
        </div>

        <!-- Dish -->
        <article
            v-else-if="item"
            class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12"
        >
            <div
                class="relative aspect-[4/3] overflow-hidden rounded border border-mori-line bg-mori-cream"
            >
                <img
                    v-if="item.image_url"
                    :src="item.image_url"
                    :alt="item.name"
                    class="h-full w-full object-cover"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center"
                    aria-hidden="true"
                >
                    <span class="font-display text-6xl text-mori-sand">
                        {{ item.name.charAt(0) }}
                    </span>
                </div>
                <AppBadge
                    v-if="item.is_featured"
                    tone="wine"
                    class="absolute top-3 left-3 bg-mori-paper/95"
                >
                    Signature
                </AppBadge>
                <AppBadge
                    v-if="isSoldOut"
                    tone="neutral"
                    class="absolute top-3 right-3 bg-mori-ink/85 text-white"
                >
                    Sold out
                </AppBadge>
            </div>

            <div class="min-w-0">
                <p
                    v-if="item.course"
                    class="text-xs font-medium tracking-[0.2em] text-mori-wine uppercase"
                >
                    {{ courseLabels[item.course] ?? item.course }}
                    <template v-if="item.category">
                        · {{ item.category.name }}
                    </template>
                </p>
                <h1
                    class="mori-heading mt-2 text-3xl text-mori-ink sm:text-4xl"
                >
                    {{ item.name }}
                </h1>
                <p class="mt-3 font-display text-2xl text-mori-wine">
                    {{ formatMoney(item.price) }}
                </p>

                <p
                    v-if="item.description"
                    class="mt-4 max-w-prose text-sm leading-relaxed text-mori-muted sm:text-base"
                >
                    {{ item.description }}
                </p>

                <ul
                    v-if="dietaryTags.length"
                    class="mt-4 flex flex-wrap gap-1.5"
                    aria-label="Dietary classification"
                >
                    <li
                        v-for="tag in dietaryTags"
                        :key="tag.id"
                        :title="tag.description ?? undefined"
                    >
                        <AppBadge tone="sage">{{ tag.name }}</AppBadge>
                    </li>
                </ul>

                <!-- Ingredients and allergens -->
                <section
                    class="mt-6 space-y-4 rounded border border-mori-line bg-mori-paper p-5"
                    aria-labelledby="ingredients-heading"
                >
                    <h2
                        id="ingredients-heading"
                        class="text-xs font-semibold tracking-[0.2em] text-mori-ink uppercase"
                    >
                        Ingredients &amp; allergens
                    </h2>

                    <div>
                        <h3 class="text-sm font-medium text-mori-ink">
                            Ingredients
                        </h3>
                        <p
                            v-if="item.ingredients"
                            class="mt-1 text-sm text-mori-muted"
                        >
                            {{ item.ingredients }}
                        </p>
                        <p v-else class="mt-1 text-sm text-mori-muted">
                            The kitchen has not recorded the ingredients for
                            this dish.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-sm font-medium text-mori-ink">
                            Allergens
                        </h3>

                        <ul
                            v-if="allergenState === 'listed'"
                            class="mt-2 flex flex-wrap gap-1.5"
                        >
                            <li
                                v-for="allergen in item.allergens ?? []"
                                :key="allergen.value"
                            >
                                <!-- A recorded allergen that matches the
                                     customer's saved list is marked, not
                                     rewritten: the label stays honest. -->
                                <AppBadge
                                    :tone="
                                        matchedAllergens.has(allergen.value)
                                            ? 'red'
                                            : 'amber'
                                    "
                                >
                                    {{ allergen.label }}
                                    <span
                                        v-if="
                                            matchedAllergens.has(allergen.value)
                                        "
                                        class="font-normal"
                                    >
                                        · on your list
                                    </span>
                                </AppBadge>
                            </li>
                        </ul>

                        <p
                            v-else-if="allergenState === 'none'"
                            class="mt-1 text-sm text-mori-muted"
                        >
                            None of the tracked allergen groups — peanuts, tree
                            nuts, shellfish, fish, eggs, milk, soy, wheat and
                            sesame — are recorded for this dish.
                        </p>

                        <p v-else class="mt-1 text-sm text-mori-muted">
                            Allergen information has not been recorded for this
                            dish. Ask our staff before ordering.
                        </p>
                    </div>

                    <p
                        class="flex gap-2 rounded bg-mori-cream/70 px-3 py-2.5 text-xs leading-relaxed text-mori-muted"
                        role="note"
                    >
                        <TriangleAlert
                            class="mt-0.5 size-4 shrink-0 text-mori-wine"
                            aria-hidden="true"
                        />
                        <span>
                            This is a shared kitchen and cross-contamination can
                            occur. A dish that does not list an allergen is not
                            guaranteed to be safe for you — if you have a
                            serious allergy, please confirm with restaurant
                            staff before ordering.
                        </span>
                    </p>
                </section>

                <!--
                    The customer's own restrictions. Guests see a login prompt
                    instead, because restrictions are personal data and are
                    never hardcoded into the page.
                -->
                <section
                    class="mt-6 rounded border p-5"
                    :class="
                        hasConfirmedMatch
                            ? 'border-mori-wine/40 bg-mori-wine/5'
                            : 'border-mori-line bg-mori-paper'
                    "
                    aria-labelledby="your-restrictions-heading"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <h2
                            id="your-restrictions-heading"
                            class="text-xs font-semibold tracking-[0.2em] text-mori-ink uppercase"
                        >
                            Your dietary restrictions
                        </h2>
                        <Link
                            href="/account/dietary-restrictions"
                            class="text-xs font-medium text-mori-wine underline underline-offset-4 hover:text-mori-wine-hover focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                        >
                            {{
                                isAuthenticated
                                    ? 'Update restrictions'
                                    : 'Log in to save yours'
                            }}
                        </Link>
                    </div>

                    <!-- Guest -->
                    <template v-if="!isAuthenticated">
                        <p class="mt-2 text-sm leading-relaxed text-mori-muted">
                            <Link
                                href="/login"
                                class="font-medium text-mori-wine underline underline-offset-4"
                            >
                                Log in
                            </Link>
                            or
                            <Link
                                href="/register"
                                class="font-medium text-mori-wine underline underline-offset-4"
                            >
                                create an account
                            </Link>
                            to save your allergies and dietary needs, then see
                            them checked against every dish. The general
                            allergen information above is available without an
                            account.
                        </p>
                    </template>

                    <!-- Signed in, nothing saved -->
                    <template v-else-if="savedRestrictions.length === 0">
                        <p class="mt-2 text-sm leading-relaxed text-mori-muted">
                            You have not saved any allergies or dietary
                            restrictions.
                            <Link
                                href="/account/dietary-restrictions"
                                class="font-medium text-mori-wine underline underline-offset-4"
                            >
                                Add them
                            </Link>
                            to compare them with this dish next time.
                        </p>
                    </template>

                    <!-- Signed in with saved restrictions -->
                    <template v-else>
                        <ul class="mt-3 space-y-2" aria-live="polite">
                            <li
                                v-for="match in restrictionMatches"
                                :key="match.restriction"
                                class="flex gap-2 text-sm leading-relaxed"
                            >
                                <TriangleAlert
                                    v-if="match.reason === 'allergen'"
                                    class="mt-0.5 size-4 shrink-0 text-mori-wine"
                                    aria-hidden="true"
                                />
                                <Check
                                    v-else-if="match.reason === 'matches-diet'"
                                    class="mt-0.5 size-4 shrink-0 text-mori-sage"
                                    aria-hidden="true"
                                />
                                <TriangleAlert
                                    v-else
                                    class="mt-0.5 size-4 shrink-0 text-mori-muted"
                                    aria-hidden="true"
                                />

                                <span
                                    :class="
                                        match.reason === 'allergen'
                                            ? 'text-mori-ink'
                                            : 'text-mori-muted'
                                    "
                                >
                                    <span class="font-medium text-mori-ink">
                                        {{ match.label }}
                                    </span>
                                    <template
                                        v-if="match.reason === 'allergen'"
                                    >
                                        — recorded in this dish. Confirm with
                                        staff before ordering.
                                    </template>
                                    <template
                                        v-else-if="
                                            match.reason === 'matches-diet'
                                        "
                                    >
                                        — this dish is classified
                                        {{ match.dietary_tag }}.
                                    </template>
                                    <template v-else>
                                        — this dish is not classified as
                                        {{ match.dietary_tag }}. Ask staff
                                        whether it can be adapted.
                                    </template>
                                </span>
                            </li>
                        </ul>

                        <p
                            v-if="
                                restrictionMatches.length === 0 &&
                                allergenState !== 'unrecorded'
                            "
                            class="mt-3 text-sm text-mori-muted"
                        >
                            None of your saved allergies appear in the recorded
                            allergens for this dish. This is not a guarantee —
                            see the cross-contamination note above.
                        </p>

                        <p
                            v-if="allergenState === 'unrecorded'"
                            class="mt-3 rounded bg-mori-cream/70 px-3 py-2 text-xs leading-relaxed text-mori-ink"
                            role="alert"
                        >
                            <span class="font-medium"
                                >Information unavailable.</span
                            >
                            The kitchen has not recorded allergens for this
                            dish, so we cannot check it against your saved
                            allergies. Please ask staff before ordering.
                        </p>

                        <p
                            v-if="preferencesNotMet.length > 0"
                            class="mt-2 text-xs text-mori-muted"
                        >
                            Dietary preferences are not safety checks; they
                            describe how a dish is classified on this menu.
                        </p>

                        <p class="mt-3 text-xs text-mori-muted">
                            Your saved list:
                            <span class="text-mori-ink">
                                {{
                                    savedRestrictions
                                        .map((entry) => entry.label)
                                        .join(', ')
                                }}
                            </span>
                        </p>
                    </template>

                    <p
                        class="mt-3 border-t border-mori-line pt-3 text-xs leading-relaxed text-mori-muted"
                        role="note"
                    >
                        We never label a dish as safe. Recipes list the allergen
                        groups they contain, the kitchen is shared, and
                        information can be incomplete.
                    </p>
                </section>

                <!-- Order -->
                <section class="mt-6" aria-labelledby="order-heading">
                    <h2 id="order-heading" class="sr-only">Add to order</h2>

                    <div
                        v-if="customizationGroups.length"
                        class="mb-5 space-y-5"
                    >
                        <fieldset
                            v-for="group in customizationGroups"
                            :key="group.id"
                            class="space-y-2"
                        >
                            <legend class="text-sm font-semibold text-mori-ink">
                                {{ group.name }}
                            </legend>

                            <div
                                v-if="group.type === 'addons'"
                                class="flex flex-wrap gap-2"
                            >
                                <button
                                    v-for="option in group.options"
                                    :key="option.id"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-sm transition-colors"
                                    :class="
                                        (
                                            selectedOptions[group.id] ?? []
                                        ).includes(option.id)
                                            ? 'border-mori-wine bg-mori-wine text-white'
                                            : 'border-mori-line text-mori-ink hover:border-mori-wine'
                                    "
                                    :aria-pressed="
                                        (
                                            selectedOptions[group.id] ?? []
                                        ).includes(option.id)
                                    "
                                    @click="
                                        toggleOption(group.id, option.id, false)
                                    "
                                >
                                    {{ option.name }}
                                    <span
                                        v-if="Number(option.price) > 0"
                                        class="ml-1 opacity-80"
                                    >
                                        +{{ formatMoney(String(option.price)) }}
                                    </span>
                                </button>
                            </div>

                            <div v-else class="space-y-2">
                                <label
                                    v-for="option in group.options"
                                    :key="option.id"
                                    class="flex cursor-pointer items-center gap-3 rounded border border-mori-line px-3 py-2.5 text-sm text-mori-ink hover:bg-mori-cream/60"
                                >
                                    <input
                                        :type="
                                            group.type === 'radio'
                                                ? 'radio'
                                                : 'checkbox'
                                        "
                                        :name="`dish-${item.id}-${group.id}`"
                                        :checked="
                                            (
                                                selectedOptions[group.id] ?? []
                                            ).includes(option.id)
                                        "
                                        @change="
                                            toggleOption(
                                                group.id,
                                                option.id,
                                                group.type === 'radio',
                                            )
                                        "
                                    />
                                    <span class="flex-1">{{
                                        option.name
                                    }}</span>
                                    <span
                                        v-if="Number(option.price) > 0"
                                        class="text-mori-muted"
                                    >
                                        +{{ formatMoney(String(option.price)) }}
                                    </span>
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <div
                            class="inline-flex items-center rounded border border-mori-line"
                            role="group"
                            aria-label="Quantity"
                        >
                            <button
                                type="button"
                                class="inline-flex size-11 items-center justify-center text-mori-ink transition-colors hover:bg-mori-cream focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-mori-wine disabled:opacity-40"
                                :disabled="quantity <= 1 || isSoldOut"
                                aria-label="Decrease quantity"
                                @click="changeQuantity(-1)"
                            >
                                <Minus class="size-4" aria-hidden="true" />
                            </button>
                            <span
                                class="w-10 text-center text-sm font-medium tabular-nums"
                                aria-live="polite"
                            >
                                {{ quantity }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex size-11 items-center justify-center text-mori-ink transition-colors hover:bg-mori-cream focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-mori-wine disabled:opacity-40"
                                :disabled="quantity >= 99 || isSoldOut"
                                aria-label="Increase quantity"
                                @click="changeQuantity(1)"
                            >
                                <Plus class="size-4" aria-hidden="true" />
                            </button>
                        </div>

                        <p class="text-sm text-mori-muted">
                            Line total
                            <span
                                class="ml-1 font-display text-lg text-mori-ink"
                            >
                                {{ formatMoney(lineTotal) }}
                            </span>
                        </p>
                    </div>

                    <label
                        for="dish-note"
                        class="mt-4 block text-sm font-medium text-mori-ink"
                    >
                        Special instructions
                        <span class="font-normal text-mori-muted">
                            (optional)
                        </span>
                    </label>
                    <Textarea
                        id="dish-note"
                        v-model="note"
                        class="mori-field mt-1.5"
                        rows="2"
                        maxlength="255"
                        placeholder="No onion, extra sauce, allergies…"
                    />

                    <AppButton
                        class="mt-4"
                        block
                        size="lg"
                        :loading="adding"
                        :disabled="isSoldOut"
                        @click="addToOrder"
                    >
                        <Check v-if="added" class="size-4" aria-hidden="true" />
                        <Plus v-else class="size-4" aria-hidden="true" />
                        {{
                            isSoldOut
                                ? 'Currently unavailable'
                                : added
                                  ? 'Added to order'
                                  : 'Add to order'
                        }}
                    </AppButton>

                    <p
                        class="mt-2 text-xs text-mori-muted"
                        role="status"
                        aria-live="polite"
                    >
                        <template v-if="added">
                            Added to your order.
                            <Link href="/checkout" class="underline">
                                Review and place your order
                            </Link>
                            when you're ready.
                        </template>
                        <template v-else>
                            Adding a dish only starts your order — nothing is
                            placed until you check out.
                        </template>
                    </p>
                </section>
            </div>
        </article>
    </div>
</template>
