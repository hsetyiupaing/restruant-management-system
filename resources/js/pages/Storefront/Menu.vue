<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import FoodCard from '@/components/mori/FoodCard.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import SectionHeading from '@/components/mori/SectionHeading.vue';
import { api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import { useCart } from '@/composables/useCart';
import type { Category, MenuItem, Paginated, Restaurant } from '@/types/restaurant';

const props = defineProps<{
    restaurant: Restaurant | null;
    categories: Category[];
}>();

const page = usePage();
const { itemCount, subtotal } = useCart();

const dishes = ref<MenuItem[]>([]);
const meta = ref<Paginated<MenuItem>['meta'] | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

const initialQuery = new URLSearchParams(page.url.split('?')[1] ?? '');

const filters = reactive({
    search: initialQuery.get('search') ?? '',
    categoryId: initialQuery.get('category_id') ?? '',
    page: 1,
});

let searchTimer: number | undefined;

const activeCategoryName = computed(() => {
    if (!filters.categoryId) {
        return 'All dishes';
    }

    return (
        props.categories.find((c) => String(c.id) === filters.categoryId)?.name ??
        'All dishes'
    );
});

async function load(): Promise<void> {
    if (!props.restaurant) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<MenuItem>>(
            `/restaurants/${props.restaurant.slug}/menu-items`,
            {
                query: {
                    per_page: 60,
                    category_id: filters.categoryId || undefined,
                    search: filters.search || undefined,
                    page: filters.page,
                },
            },
        );

        dishes.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

/**
 * Filters live in the URL so a reload or a shared link keeps the same view.
 */
function syncUrl(): void {
    const params = new URLSearchParams();

    if (filters.categoryId) {
        params.set('category_id', filters.categoryId);
    }

    if (filters.search) {
        params.set('search', filters.search);
    }

    const query = params.toString();
    const url = `${window.location.pathname}${query ? `?${query}` : ''}`;

    window.history.replaceState({}, '', url);
}

/**
 * Typing is debounced so the menu is not refetched on every keystroke;
 * category changes are applied immediately.
 */
watch(
    () => filters.categoryId,
    () => {
        filters.page = 1;
        syncUrl();
        void load();
    },
);

watch(
    () => filters.search,
    () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            filters.page = 1;
            syncUrl();
            void load();
        }, 300);
    },
);

watch(
    () => filters.page,
    () => void load(),
);

onMounted(() => {
    if (props.restaurant) {
        void load();
    }
});

function clearFilters(): void {
    filters.search = '';
    filters.categoryId = '';
    filters.page = 1;
}
</script>

<template>
    <Head title="Menu · MORI" />

    <section class="border-b border-mori-line bg-mori-cream/60">
        <div class="mori-container py-12">
            <SectionHeading
                eyebrow="Today's menu"
                title="Everything on the pass"
                :text="
                    restaurant
                        ? `${restaurant.name} · pickup and delivery available`
                        : undefined
                "
            />
        </div>
    </section>

    <div class="mori-container grid gap-8 py-10 lg:grid-cols-[1fr_320px]">
        <div>
            <div class="flex flex-col gap-4">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-mori-muted"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="filters.search"
                        type="search"
                        placeholder="Search dishes…"
                        aria-label="Search dishes"
                        class="h-11 rounded border-mori-line bg-mori-paper pl-9"
                    />
                </div>

                <div
                    class="flex flex-wrap gap-2"
                    role="group"
                    aria-label="Filter by category"
                >
                    <button
                        type="button"
                        class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                        :class="
                            !filters.categoryId
                                ? 'border-mori-ink bg-mori-ink text-mori-paper'
                                : 'border-mori-line text-mori-muted hover:border-mori-ink'
                        "
                        :aria-pressed="!filters.categoryId"
                        @click="filters.categoryId = ''"
                    >
                        All
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        class="rounded-full border px-4 py-1.5 text-sm transition-colors"
                        :class="
                            String(category.id) === filters.categoryId
                                ? 'border-mori-ink bg-mori-ink text-mori-paper'
                                : 'border-mori-line text-mori-muted hover:border-mori-ink'
                        "
                        :aria-pressed="String(category.id) === filters.categoryId"
                        @click="filters.categoryId = String(category.id)"
                    >
                        {{ category.name }}
                        <span class="ml-1 text-xs opacity-70">
                            {{ category.menu_items_count ?? 0 }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between gap-4">
                <h2 class="mori-heading text-xl">{{ activeCategoryName }}</h2>
                <span class="text-sm text-mori-muted">
                    {{ meta?.total ?? dishes.length }} dishes
                </span>
            </div>

            <p
                v-if="error"
                class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ error }}
            </p>

            <div
                v-if="loading"
                class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
                aria-busy="true"
            >
                <div
                    v-for="index in 6"
                    :key="index"
                    class="animate-pulse rounded border border-mori-line bg-mori-paper"
                >
                    <div class="aspect-[4/3] bg-mori-cream" />
                    <div class="space-y-2 p-4">
                        <div class="h-4 w-2/3 rounded bg-mori-cream" />
                        <div class="h-3 w-full rounded bg-mori-cream" />
                        <div class="h-3 w-4/5 rounded bg-mori-cream" />
                    </div>
                </div>
            </div>

            <div
                v-else-if="dishes.length"
                class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
            >
                <FoodCard v-for="dish in dishes" :key="dish.id" :dish="dish" />
            </div>

            <EmptyState
                v-else
                class="mt-6"
                title="No dishes match that search"
                description="Try a different category or clear the search."
            >
                <button
                    v-if="filters.search || filters.categoryId"
                    type="button"
                    class="inline-flex items-center gap-1 text-sm font-medium text-mori-wine underline"
                    @click="clearFilters"
                >
                    <X class="size-4" aria-hidden="true" /> Clear filters
                </button>
            </EmptyState>

            <div
                v-if="meta && meta.last_page > 1"
                class="mt-8 flex items-center justify-center gap-2"
            >
                <button
                    type="button"
                    class="rounded border border-mori-line px-3 py-1.5 text-sm disabled:opacity-40"
                    :disabled="meta.current_page <= 1"
                    @click="filters.page = meta.current_page - 1"
                >
                    Previous
                </button>
                <span class="text-sm text-mori-muted">
                    Page {{ meta.current_page }} of {{ meta.last_page }}
                </span>
                <button
                    type="button"
                    class="rounded border border-mori-line px-3 py-1.5 text-sm disabled:opacity-40"
                    :disabled="meta.current_page >= meta.last_page"
                    @click="filters.page = meta.current_page + 1"
                >
                    Next
                </button>
            </div>
        </div>

        <aside class="lg:sticky lg:top-28 lg:self-start">
            <div class="rounded border border-mori-line bg-mori-paper p-5">
                <h2 class="mori-heading text-lg">Your order</h2>
                <p v-if="itemCount === 0" class="mt-2 text-sm text-mori-muted">
                    Nothing added yet.
                </p>
                <template v-else>
                    <p class="mt-2 text-sm text-mori-muted">
                        {{ itemCount }} item{{ itemCount === 1 ? '' : 's' }}
                    </p>
                    <p class="mt-1 font-display text-xl text-mori-ink">
                        {{ formatMoney(subtotal) }}
                    </p>
                    <p class="mt-1 text-xs text-mori-muted">
                        Estimate only — the restaurant confirms the final total.
                    </p>
                </template>
                <Link
                    href="/checkout"
                    class="mt-4 inline-flex h-11 w-full items-center justify-center rounded bg-mori-wine text-sm font-medium text-white hover:bg-mori-wine-hover"
                    :class="itemCount === 0 ? 'pointer-events-none opacity-50' : ''"
                >
                    Go to checkout
                </Link>
            </div>
        </aside>
    </div>
</template>
