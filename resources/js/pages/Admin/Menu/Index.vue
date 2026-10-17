<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppButton from '@/components/mori/AppButton.vue';
import AppModal from '@/components/mori/AppModal.vue';
import DataTable from '@/components/mori/DataTable.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import type { Category, MenuItem, Paginated } from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const base = computed(() => `/admin/restaurants/${props.restaurant.id}`);

const categories = ref<Category[]>([]);
const items = ref<MenuItem[]>([]);
const meta = ref<Paginated<MenuItem>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const filters = reactive({
    categoryId: '',
    search: '',
    page: 1,
});

const columns = [
    { key: 'name', label: 'Dish' },
    { key: 'category', label: 'Category' },
    { key: 'price', label: 'Price', align: 'right' as const },
    { key: 'is_available', label: 'Available', align: 'center' as const },
    { key: 'is_featured', label: 'Signature', align: 'center' as const },
    { key: 'actions', label: '', align: 'right' as const },
];

const rows = computed(() => items.value as unknown as Record<string, unknown>[]);

function categoryName(row: Record<string, unknown>): string {
    return (row.category as { name?: string } | undefined)?.name ?? '—';
}

function asItem(row: Record<string, unknown>): MenuItem {
    return row as unknown as MenuItem;
}

/* ---------------- Category management ---------------- */

const categoryOpen = ref(false);
const categorySaving = ref(false);
const categoryForm = reactive({ id: null as number | null, name: '', sort_order: 0 });

function openCategory(category?: Category): void {
    categoryForm.id = category?.id ?? null;
    categoryForm.name = category?.name ?? '';
    categoryForm.sort_order = category?.sort_order ?? 0;
    categoryOpen.value = true;
}

async function saveCategory(): Promise<void> {
    categorySaving.value = true;

    try {
        const body = {
            name: categoryForm.name.trim(),
            sort_order: categoryForm.sort_order,
        };

        if (categoryForm.id === null) {
            await api.post(`${base.value}/categories`, body);
            toast.success('Category created.');
        } else {
            await api.patch(`${base.value}/categories/${categoryForm.id}`, body);
            toast.success('Category updated.');
        }

        categoryOpen.value = false;
        await loadCategories();
    } catch (caught) {
        toast.error(errorMessage(caught));
    } finally {
        categorySaving.value = false;
    }
}

async function deleteCategory(category: Category): Promise<void> {
    try {
        await api.delete(`${base.value}/categories/${category.id}`);
        toast.success('Category deleted.');
        await loadCategories();
    } catch (caught) {
        toast.error(errorMessage(caught));
    }
}

/* ---------------- Menu item management ---------------- */

const itemOpen = ref(false);
const itemSaving = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

const itemForm = reactive({
    id: null as number | null,
    category_id: '',
    name: '',
    description: '',
    price: '',
    image_url: '',
    is_available: true,
    is_featured: false,
});

function openItem(item?: MenuItem): void {
    fieldErrors.value = {};
    itemForm.id = item?.id ?? null;
    itemForm.category_id = item ? String(item.category_id) : (filters.categoryId || '');
    itemForm.name = item?.name ?? '';
    itemForm.description = item?.description ?? '';
    itemForm.price = item?.price ?? '';
    itemForm.image_url = item?.image_url ?? '';
    itemForm.is_available = item?.is_available ?? true;
    itemForm.is_featured = item?.is_featured ?? false;
    itemOpen.value = true;
}

async function saveItem(): Promise<void> {
    itemSaving.value = true;
    fieldErrors.value = {};

    const body = {
        category_id: Number(itemForm.category_id),
        name: itemForm.name.trim(),
        description: itemForm.description.trim() || null,
        price: Number.parseFloat(itemForm.price || '0'),
        image_url: itemForm.image_url.trim() || null,
        is_available: itemForm.is_available,
        is_featured: itemForm.is_featured,
    };

    try {
        if (itemForm.id === null) {
            await api.post(`${base.value}/menu-items`, body);
            toast.success('Dish created.');
        } else {
            await api.patch(`${base.value}/menu-items/${itemForm.id}`, body);
            toast.success('Dish updated.');
        }

        itemOpen.value = false;
        await loadItems();
    } catch (caught) {
        if (caught instanceof ApiError) {
            fieldErrors.value = caught.errors;
        }

        toast.error(errorMessage(caught));
    } finally {
        itemSaving.value = false;
    }
}

async function toggleAvailability(item: MenuItem): Promise<void> {
    try {
        const response = await api.patch<{ data: MenuItem }>(
            `${base.value}/menu-items/${item.id}/availability`,
            { is_available: !item.is_available },
        );

        items.value = items.value.map((entry) =>
            entry.id === item.id ? response.data : entry,
        );
    } catch (caught) {
        toast.error(errorMessage(caught));
    }
}

async function deleteItem(item: MenuItem): Promise<void> {
    try {
        await api.delete(`${base.value}/menu-items/${item.id}`);
        toast.success('Dish removed.');
        await loadItems();
    } catch (caught) {
        toast.error(errorMessage(caught));
    }
}

function fieldError(field: string): string | null {
    return fieldErrors.value[field]?.[0] ?? null;
}

/* ---------------- Loading ---------------- */

async function loadCategories(): Promise<void> {
    try {
        const response = await api.get<{ data: Category[] }>(
            `${base.value}/categories`,
        );

        categories.value = response.data;
    } catch (caught) {
        error.value = errorMessage(caught);
    }
}

async function loadItems(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<MenuItem>>(
            `${base.value}/menu-items`,
            {
                query: {
                    category_id: filters.categoryId || undefined,
                    search: filters.search || undefined,
                    page: filters.page,
                    per_page: 50,
                },
            },
        );

        items.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    void loadCategories();
    void loadItems();
});
</script>

<template>
    <Head title="Menu · MORI Admin" />

    <AdminTopbar title="Menu" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <p
            v-if="!canManageMenu"
            class="mb-4 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            role="status"
        >
            You can view the menu but not change it. Ask a restaurant
            administrator for access.
        </p>

        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <aside class="rounded border border-[#e3e5df] bg-white">
                <div class="flex items-center justify-between border-b border-[#e3e5df] px-4 py-3">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        Categories
                    </h2>
                    <AppButton
                        v-if="canManageMenu"
                        variant="ghost"
                        size="sm"
                        :icon="Plus"
                        @click="openCategory()"
                    >
                        New
                    </AppButton>
                </div>

                <ul class="divide-y divide-[#e3e5df]/60">
                    <li>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm"
                            :class="
                                !filters.categoryId
                                    ? 'bg-mori-cream/60 font-medium text-mori-ink'
                                    : 'text-mori-muted hover:bg-mori-cream/40'
                            "
                            @click="
                                filters.categoryId = '';
                                filters.page = 1;
                                loadItems();
                            "
                        >
                            All dishes
                        </button>
                    </li>
                    <li
                        v-for="category in categories"
                        :key="category.id"
                        class="group flex items-center justify-between px-2 py-1"
                    >
                        <button
                            type="button"
                            class="flex-1 rounded px-2 py-1.5 text-left text-sm"
                            :class="
                                filters.categoryId === String(category.id)
                                    ? 'bg-mori-cream/60 font-medium text-mori-ink'
                                    : 'text-mori-muted hover:bg-mori-cream/40'
                            "
                            @click="
                                filters.categoryId = String(category.id);
                                filters.page = 1;
                                loadItems();
                            "
                        >
                            {{ category.name }}
                            <span class="ml-1 text-xs opacity-60">
                                {{ category.menu_items_count ?? 0 }}
                            </span>
                        </button>
                        <span
                            v-if="canManageMenu"
                            class="flex shrink-0 gap-0.5 opacity-0 group-hover:opacity-100 focus-within:opacity-100"
                        >
                            <button
                                type="button"
                                class="rounded p-1 text-mori-muted hover:bg-mori-cream hover:text-mori-ink"
                                :aria-label="`Edit ${category.name}`"
                                @click="openCategory(category)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                class="rounded p-1 text-mori-muted hover:bg-red-50 hover:text-red-700"
                                :aria-label="`Delete ${category.name}`"
                                @click="deleteCategory(category)"
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                            </button>
                        </span>
                    </li>
                </ul>
            </aside>

            <div>
                <div class="flex flex-wrap items-end gap-3 rounded border border-[#e3e5df] bg-white p-4">
                    <div class="min-w-48 flex-1 space-y-1.5">
                        <Label for="search">Search</Label>
                        <Input
                            id="search"
                            v-model="filters.search"
                            type="search"
                            placeholder="Dish name…"
                            @keyup.enter="
                                filters.page = 1;
                                loadItems();
                            "
                        />
                    </div>
                    <AppButton
                        v-if="canManageMenu"
                        :icon="Plus"
                        @click="openItem()"
                    >
                        New dish
                    </AppButton>
                </div>

                <p
                    v-if="error"
                    class="mt-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                    role="alert"
                >
                    {{ error }}
                </p>

                <div class="mt-4">
                    <DataTable
                        :columns="columns"
                        :rows="rows"
                        row-key="id"
                        :loading="loading"
                        striped
                        empty-title="No dishes"
                        empty-description="Add a dish to start building the menu."
                    >
                        <template #cell-name="{ row }">
                            <div>
                                <p class="font-medium text-mori-ink">
                                    {{ row.name }}
                                </p>
                                <p
                                    v-if="row.description"
                                    class="line-clamp-1 text-xs text-mori-muted"
                                >
                                    {{ row.description }}
                                </p>
                            </div>
                        </template>

                        <template #cell-category="{ row }">
                            <span class="text-xs tracking-wide text-mori-muted uppercase">
                                {{ categoryName(row) }}
                            </span>
                        </template>

                        <template #cell-price="{ row }">
                            {{ formatMoney(String(row.price)) }}
                        </template>

                        <template #cell-is_available="{ row }">
                            <button
                                type="button"
                                class="rounded-sm px-2 py-0.5 text-xs font-medium"
                                :class="
                                    row.is_available
                                        ? 'bg-emerald-100 text-emerald-900'
                                        : 'bg-mori-cream text-mori-muted'
                                "
                                :disabled="!canManageMenu"
                                :aria-label="`Toggle availability for ${row.name}`"
                                @click="toggleAvailability(asItem(row))"
                            >
                                {{ row.is_available ? 'Available' : 'Sold out' }}
                            </button>
                        </template>

                        <template #cell-is_featured="{ row }">
                            <span
                                class="text-xs"
                                :class="row.is_featured ? 'text-mori-wine' : 'text-mori-muted'"
                            >
                                {{ row.is_featured ? 'Signature' : '—' }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <div v-if="canManageMenu" class="flex justify-end gap-1">
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-mori-muted hover:bg-mori-cream hover:text-mori-ink"
                                    :aria-label="`Edit ${row.name}`"
                                    @click="openItem(asItem(row))"
                                >
                                    <Pencil class="size-4" aria-hidden="true" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded p-1.5 text-mori-muted hover:bg-red-50 hover:text-red-700"
                                    :aria-label="`Delete ${row.name}`"
                                    @click="deleteItem(asItem(row))"
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        </template>
                    </DataTable>
                </div>

                <div
                    v-if="meta && meta.last_page > 1"
                    class="mt-4 flex justify-end gap-2 text-sm"
                >
                    <button
                        type="button"
                        class="rounded border border-[#e3e5df] bg-white px-3 py-1.5 disabled:opacity-40"
                        :disabled="meta.current_page <= 1"
                        @click="
                            filters.page = meta.current_page - 1;
                            loadItems();
                        "
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded border border-[#e3e5df] bg-white px-3 py-1.5 disabled:opacity-40"
                        :disabled="meta.current_page >= meta.last_page"
                        @click="
                            filters.page = meta.current_page + 1;
                            loadItems();
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <AppModal
            v-model:open="categoryOpen"
            :title="categoryForm.id === null ? 'New category' : 'Edit category'"
        >
            <div class="space-y-4">
                <div class="space-y-1.5">
                    <Label for="category-name">Name</Label>
                    <Input id="category-name" v-model="categoryForm.name" maxlength="255" />
                </div>
                <div class="space-y-1.5">
                    <Label for="category-sort">Sort order</Label>
                    <Input
                        id="category-sort"
                        v-model.number="categoryForm.sort_order"
                        type="number"
                        min="0"
                    />
                </div>
            </div>
            <template #footer>
                <AppButton variant="outline" @click="categoryOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    :loading="categorySaving"
                    :disabled="!categoryForm.name.trim()"
                    @click="saveCategory"
                >
                    Save
                </AppButton>
            </template>
        </AppModal>

        <AppModal
            v-model:open="itemOpen"
            :title="itemForm.id === null ? 'New dish' : 'Edit dish'"
            wide
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="item-name">Name</Label>
                    <Input id="item-name" v-model="itemForm.name" maxlength="255" />
                    <p v-if="fieldError('name')" class="text-xs text-red-700">
                        {{ fieldError('name') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-category">Category</Label>
                    <select
                        id="item-category"
                        v-model="itemForm.category_id"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="" disabled>Choose a category</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <p v-if="fieldError('category_id')" class="text-xs text-red-700">
                        {{ fieldError('category_id') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-price">Price (THB)</Label>
                    <Input
                        id="item-price"
                        v-model="itemForm.price"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                    <p v-if="fieldError('price')" class="text-xs text-red-700">
                        {{ fieldError('price') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-image">Image URL</Label>
                    <Input
                        id="item-image"
                        v-model="itemForm.image_url"
                        type="url"
                        placeholder="https://…"
                    />
                    <p v-if="fieldError('image_url')" class="text-xs text-red-700">
                        {{ fieldError('image_url') }}
                    </p>
                </div>
                <div class="space-y-1.5 sm:col-span-2">
                    <Label for="item-description">Description</Label>
                    <Textarea
                        id="item-description"
                        v-model="itemForm.description"
                        rows="3"
                        maxlength="5000"
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-mori-ink">
                    <input v-model="itemForm.is_available" type="checkbox" />
                    Available to order
                </label>
                <label class="flex items-center gap-2 text-sm text-mori-ink">
                    <input v-model="itemForm.is_featured" type="checkbox" />
                    Feature on the storefront
                </label>
            </div>
            <template #footer>
                <AppButton variant="outline" @click="itemOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    :loading="itemSaving"
                    :disabled="!itemForm.name.trim() || !itemForm.category_id"
                    @click="saveItem"
                >
                    Save dish
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
