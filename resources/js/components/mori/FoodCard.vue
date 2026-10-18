<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppBadge from '@/components/mori/AppBadge.vue';
import AppModal from '@/components/mori/AppModal.vue';
import { useCart } from '@/composables/useCart';
import { formatMoney } from '@/lib/format';
import type { MenuItem } from '@/types/restaurant';

const props = defineProps<{
    dish: MenuItem;
    showAdd?: boolean;
}>();

const { add } = useCart();
const customizing = ref(false);
const note = ref('');
const added = ref(false);
const selectedOptions = ref<Record<string, string[]>>({});
const customizationGroups = computed(
    () => props.dish.customization_groups ?? [],
);

const isSoldOut = computed(() => !props.dish.is_available);

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

    return Number(props.dish.price) + extraPrice;
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

/**
 * The card links to the dish detail page. Dishes created before slugs were
 * introduced fall back to their numeric id, which the API also accepts.
 */
const dishHref = computed(() => `/menu/${props.dish.slug ?? props.dish.id}`);

const allergensLabel = computed(() => {
    const allergens = props.dish.allergens;

    if (allergens === undefined || allergens === null) {
        return null;
    }

    return allergens.map((allergen) => allergen.label).join(', ');
});

/**
 * Dietary classifications are informational. Keto reads as a lighter signal;
 * the plant-based ones are the stronger "fits your diet" cue.
 */
function tagTone(slug: string): 'sage' | 'sand' {
    return slug === 'keto' ? 'sand' : 'sage';
}

function toggleOption(groupId: string, optionId: string, radio: boolean): void {
    const selected = selectedOptions.value[groupId] ?? [];

    selectedOptions.value = {
        ...selectedOptions.value,
        [groupId]: radio
            ? selected.includes(optionId)
                ? []
                : [optionId]
            : selected.includes(optionId)
              ? selected.filter((selectedId) => selectedId !== optionId)
              : [...selected, optionId],
    };
}

watch(customizing, (isOpen) => {
    if (!isOpen) {
        selectedOptions.value = {};
        note.value = '';
    }
});

function confirmAdd(): void {
    add(props.dish, 1, note.value.trim(), selectedCustomizations.value);
    note.value = '';
    customizing.value = false;
    added.value = true;
    window.setTimeout(() => {
        added.value = false;
    }, 1600);
}
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded border border-mori-line bg-mori-paper transition-colors focus-within:border-mori-ink/30 hover:border-mori-ink/30"
    >
        <div class="relative aspect-[4/3] overflow-hidden bg-mori-cream">
            <Link
                :href="dishHref"
                class="block h-full w-full focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-mori-wine"
                :aria-label="`View ${dish.name}`"
            >
                <img
                    v-if="dish.image_url"
                    :src="dish.image_url"
                    :alt="dish.name"
                    loading="lazy"
                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                <div
                    v-else
                    class="flex h-full w-full items-center justify-center"
                    aria-hidden="true"
                >
                    <span class="font-display text-4xl text-mori-sand">
                        {{ dish.name.charAt(0) }}
                    </span>
                </div>

                <!--
                    Hover/focus overlay for pointer and keyboard users. The
                    softened lower half keeps the image readable underneath;
                    the blurred edge blends it into the photo. Hidden on
                    touch-only devices, where the visible "View" button below
                    is the tap target instead.
                -->
                <div
                    class="pointer-events-none absolute inset-x-0 bottom-0 flex h-1/2 translate-y-2 items-end justify-center bg-gradient-to-t from-gray-900/70 via-gray-900/45 to-transparent [mask-image:linear-gradient(to_top,black_35%,transparent)] pb-4 opacity-0 backdrop-blur-[2px] transition-[opacity,transform] duration-300 ease-out group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:translate-y-0 group-hover:opacity-100 [@media(hover:none)]:hidden"
                    aria-hidden="true"
                >
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-white/50 px-4 py-1.5 text-xs font-medium tracking-[0.14em] text-white uppercase shadow-sm transition-colors group-hover:bg-white/15"
                    >
                        View Dish
                        <ArrowRight class="size-3.5" aria-hidden="true" />
                    </span>
                </div>
            </Link>

            <AppBadge
                v-if="dish.is_featured"
                tone="wine"
                class="pointer-events-none absolute top-3 left-3 bg-mori-paper/95"
            >
                Signature
            </AppBadge>
            <AppBadge
                v-if="isSoldOut"
                tone="neutral"
                class="pointer-events-none absolute top-3 right-3 bg-mori-ink/85 text-white"
            >
                Sold out
            </AppBadge>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="mori-heading text-lg leading-snug text-mori-ink">
                    <Link
                        :href="dishHref"
                        class="transition-colors hover:text-mori-wine focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                    >
                        {{ dish.name }}
                    </Link>
                </h3>
                <span
                    class="font-display text-base whitespace-nowrap text-mori-wine"
                >
                    {{ formatMoney(dish.price) }}
                </span>
            </div>
            <p
                v-if="dish.category"
                class="text-[11px] tracking-[0.16em] text-mori-muted uppercase"
            >
                {{ dish.category.name }}
            </p>
            <p class="line-clamp-2 text-sm text-mori-muted">
                {{ dish.description }}
            </p>

            <ul
                v-if="dish.dietary_tags?.length"
                class="flex flex-wrap gap-1"
                aria-label="Dietary classification"
            >
                <li
                    v-for="tag in dish.dietary_tags"
                    :key="tag.id"
                    :title="tag.description ?? undefined"
                >
                    <AppBadge :tone="tagTone(tag.slug)">{{
                        tag.name
                    }}</AppBadge>
                </li>
            </ul>

            <!--
                Always visible, so the information is never hover-only. An
                empty list is not shown as "allergen free"; only recorded
                allergens are listed, with the detail page carrying the
                cross-contamination notice.
            -->
            <p
                v-if="allergensLabel"
                class="text-xs leading-snug text-mori-muted"
            >
                <span class="font-medium text-mori-ink">Allergens:</span>
                {{ allergensLabel }}
            </p>

            <div v-if="showAdd !== false" class="mt-auto flex gap-2 pt-3">
                <Button
                    type="button"
                    size="sm"
                    :disabled="isSoldOut"
                    class="h-9 flex-1 rounded bg-mori-ink text-mori-paper hover:bg-mori-ink/90"
                    @click="customizing = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{
                        added
                            ? 'Added to order'
                            : isSoldOut
                              ? 'Unavailable'
                              : 'Add to order'
                    }}
                </Button>
                <Link
                    :href="dishHref"
                    class="inline-flex h-9 items-center justify-center gap-1 rounded border border-mori-line px-3 text-sm text-mori-ink transition-colors hover:bg-mori-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-ink"
                    :aria-label="`View details for ${dish.name}`"
                >
                    View
                    <ArrowRight class="size-3.5" aria-hidden="true" />
                </Link>
            </div>
        </div>

        <AppModal
            v-model:open="customizing"
            :title="dish.name"
            :description="`Item price: ${formatMoney(String(unitPrice))}`"
        >
            <div v-if="customizationGroups.length" class="space-y-5">
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
                                (selectedOptions[group.id] ?? []).includes(
                                    option.id,
                                )
                                    ? 'border-mori-wine bg-mori-wine text-white'
                                    : 'border-mori-line text-mori-ink hover:border-mori-wine'
                            "
                            :aria-pressed="
                                (selectedOptions[group.id] ?? []).includes(
                                    option.id,
                                )
                            "
                            @click="toggleOption(group.id, option.id, false)"
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
                                :name="`dish-${dish.id}-${group.id}`"
                                :checked="
                                    (selectedOptions[group.id] ?? []).includes(
                                        option.id,
                                    )
                                "
                                @change="
                                    toggleOption(
                                        group.id,
                                        option.id,
                                        group.type === 'radio',
                                    )
                                "
                            />
                            <span class="flex-1">{{ option.name }}</span>
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

            <label for="dish-note" class="text-sm font-medium text-mori-ink">
                Special instructions
            </label>
            <Textarea
                id="dish-note"
                v-model="note"
                maxlength="255"
                class="mori-field mt-2"
                placeholder="No onion, extra sauce, allergies…"
            />
            <template #footer>
                <Button variant="outline" @click="customizing = false">
                    Cancel
                </Button>
                <Button
                    class="bg-mori-wine text-white hover:bg-mori-wine-hover"
                    @click="confirmAdd"
                >
                    Add to order
                </Button>
            </template>
        </AppModal>
    </article>
</template>
