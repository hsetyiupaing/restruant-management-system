<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Bookmark, Pencil, Plus, Trash2 } from '@lucide/vue';
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
import { menuCourses } from '@/types/restaurant';
import type {
    Category,
    DietaryTag,
    MenuCustomizationGroup,
    MenuCustomizationMode,
    MenuCourse,
    MenuItem,
    Paginated,
} from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const base = computed(() => `/admin/restaurants/${props.restaurant.id}`);

const categories = ref<Category[]>([]);
const dietaryTags = ref<DietaryTag[]>([]);
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
    { key: 'course', label: 'Course' },
    { key: 'price', label: 'Price', align: 'right' as const },
    { key: 'is_available', label: 'Available', align: 'center' as const },
    { key: 'is_featured', label: 'Signature', align: 'center' as const },
    { key: 'actions', label: '', align: 'right' as const },
];

const rows = computed(
    () => items.value as unknown as Record<string, unknown>[],
);

let searchTimer: number | undefined;
let latestItemsRequest = 0;

function categoryName(row: Record<string, unknown>): string {
    return (row.category as { name?: string } | undefined)?.name ?? '—';
}

function asItem(row: Record<string, unknown>): MenuItem {
    return row as unknown as MenuItem;
}

/* ---------------- Category management ---------------- */

const categoryOpen = ref(false);
const categorySaving = ref(false);
const categoryForm = reactive({
    id: null as number | null,
    name: '',
    sort_order: 0,
});

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
            await api.patch(
                `${base.value}/categories/${categoryForm.id}`,
                body,
            );
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
const pastedDishImage = ref<File | null>(null);
const pastedDishImagePreview = ref<string | null>(null);

const itemForm = reactive({
    id: null as number | null,
    category_id: '',
    name: '',
    course: 'MAIN' as MenuCourse,
    description: '',
    ingredients: '',
    allergens: [] as string[],
    price: '',
    image_url: '',
    is_available: true,
    is_featured: false,
    dietary_tags: [] as string[],
    customization_groups: [] as MenuCustomizationGroup[],
});

const customizationModes: { value: MenuCustomizationMode; label: string }[] = [
    { value: 'addons', label: 'Add-on bar (multi-select)' },
    { value: 'radio', label: 'Radio (choose one)' },
    { value: 'checkbox', label: 'Checkboxes (multi-select)' },
];

const customizationGroupsValid = computed(() =>
    itemForm.customization_groups.every(
        (group) =>
            group.name.trim() !== '' &&
            group.options.length > 0 &&
            group.options.every(
                (option) =>
                    option.name.trim() !== '' &&
                    String(option.price).trim() !== '' &&
                    Number.isFinite(Number(option.price)) &&
                    Number(option.price) >= 0,
            ),
    ),
);

/**
 * The allergen vocabulary matches App\Enums\Allergen. It is a fixed list so
 * the detail page can render consistent, reviewable information.
 */
const allergenOptions = [
    { value: 'peanuts', label: 'Peanuts' },
    { value: 'tree-nuts', label: 'Tree nuts' },
    { value: 'shellfish', label: 'Shellfish' },
    { value: 'fish', label: 'Fish' },
    { value: 'eggs', label: 'Eggs' },
    { value: 'milk', label: 'Milk' },
    { value: 'soy', label: 'Soy' },
    { value: 'wheat', label: 'Wheat' },
    { value: 'sesame', label: 'Sesame' },
];

function toggleAllergen(value: string): void {
    itemForm.allergens = itemForm.allergens.includes(value)
        ? itemForm.allergens.filter((entry) => entry !== value)
        : [...itemForm.allergens, value];
}

function addCustomizationGroup(): void {
    itemForm.customization_groups.push({
        id: crypto.randomUUID(),
        name: '',
        type: 'addons',
        options: [{ id: crypto.randomUUID(), name: '', price: '0.00' }],
    });
}

function addCustomizationOption(group: MenuCustomizationGroup): void {
    group.options.push({
        id: crypto.randomUUID(),
        name: '',
        price: '0.00',
    });
}

function clearPastedDishImage(): void {
    if (pastedDishImagePreview.value) {
        URL.revokeObjectURL(pastedDishImagePreview.value);
    }

    pastedDishImage.value = null;
    pastedDishImagePreview.value = null;
}

function handleDishImagePaste(event: ClipboardEvent): void {
    const pastedImage = Array.from(event.clipboardData?.items ?? [])
        .find((item) => item.type.startsWith('image/'))
        ?.getAsFile();

    if (!pastedImage) {
        return;
    }

    event.preventDefault();

    if (!['image/jpeg', 'image/png', 'image/webp'].includes(pastedImage.type)) {
        toast.error('Paste a JPG, PNG, or WebP image.');
        return;
    }

    if (pastedImage.size > 5 * 1024 * 1024) {
        toast.error('Dish images must be 5 MB or smaller.');
        return;
    }

    clearPastedDishImage();
    pastedDishImage.value = pastedImage;
    pastedDishImagePreview.value = URL.createObjectURL(pastedImage);
}

watch(itemOpen, (isOpen) => {
    if (!isOpen) {
        clearPastedDishImage();
    }
});

function openItem(item?: MenuItem): void {
    fieldErrors.value = {};
    clearPastedDishImage();
    itemForm.id = item?.id ?? null;
    itemForm.category_id = item
        ? String(item.category_id)
        : filters.categoryId || '';
    itemForm.name = item?.name ?? '';
    itemForm.course = item?.course ?? 'MAIN';
    itemForm.description = item?.description ?? '';
    itemForm.ingredients = item?.ingredients ?? '';
    itemForm.allergens =
        item?.allergens?.map((allergen) => allergen.value) ?? [];
    itemForm.price = item?.price ?? '';
    itemForm.image_url = item?.image_url ?? '';
    itemForm.is_available = item?.is_available ?? true;
    itemForm.is_featured = item?.is_featured ?? false;
    itemForm.dietary_tags = item?.dietary_tags?.map((tag) => tag.slug) ?? [];
    itemForm.customization_groups = (item?.customization_groups ?? []).map(
        (group) => ({
            ...group,
            options: group.options.map((option) => ({
                ...option,
                price: String(option.price),
            })),
        }),
    );
    itemOpen.value = true;
}

function toggleDietaryTag(slug: string): void {
    itemForm.dietary_tags = itemForm.dietary_tags.includes(slug)
        ? itemForm.dietary_tags.filter((entry) => entry !== slug)
        : [...itemForm.dietary_tags, slug];
}

function courseLabel(course: MenuCourse | null): string {
    return menuCourses.find((entry) => entry.value === course)?.label ?? '—';
}

async function saveItem(): Promise<void> {
    itemSaving.value = true;
    fieldErrors.value = {};

    try {
        if (pastedDishImage.value) {
            const formData = new FormData();
            formData.append('image', pastedDishImage.value);

            const uploadedImage = await api.postFormData<{ image_url: string }>(
                `${base.value}/menu-items/image`,
                formData,
            );

            itemForm.image_url = uploadedImage.image_url;
            clearPastedDishImage();
        }

        const body = {
            category_id: Number(itemForm.category_id),
            name: itemForm.name.trim(),
            course: itemForm.course,
            description: itemForm.description.trim() || null,
            ingredients: itemForm.ingredients.trim() || null,
            allergens: itemForm.allergens,
            price: Number.parseFloat(itemForm.price || '0'),
            image_url: itemForm.image_url.trim() || null,
            is_available: itemForm.is_available,
            is_featured: itemForm.is_featured,
            dietary_tags: itemForm.dietary_tags,
            customization_groups: itemForm.customization_groups,
        };

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

async function loadDietaryTags(): Promise<void> {
    try {
        const response = await api.get<{ data: DietaryTag[] }>('/dietary-tags');

        dietaryTags.value = response.data;
    } catch (caught) {
        error.value = errorMessage(caught);
    }
}

async function loadItems(): Promise<void> {
    const requestId = ++latestItemsRequest;
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

        if (requestId === latestItemsRequest) {
            items.value = response.data;
            meta.value = response.meta;
        }
    } catch (caught) {
        if (requestId === latestItemsRequest) {
            error.value = errorMessage(caught);
        }
    } finally {
        if (requestId === latestItemsRequest) {
            loading.value = false;
        }
    }
}

watch(
    () => filters.search,
    () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(() => {
            filters.page = 1;
            void loadItems();
        }, 250);
    },
);

onUnmounted(() => window.clearTimeout(searchTimer));

onMounted(() => {
    void loadCategories();
    void loadDietaryTags();
    void loadItems();
});
</script>

<template>
    <Head title="Menu · MORI Admin" />

    <AdminTopbar title="Menu" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <p
            v-if="!canManageMenu"
            class="mb-4 rounded border border-[#e8d3a3] bg-[#fbf0d9] px-4 py-3 text-sm text-[#6b4a12]"
            role="status"
        >
            You can view the menu but not change it. Ask a restaurant
            administrator for access.
        </p>

        <div class="grid gap-4 lg:grid-cols-[280px_1fr]">
            <aside class="report-card">
                <div
                    class="flex items-center justify-between border-b border-mori-line px-4 py-3"
                >
                    <h2 class="report-card-title">Categories</h2>
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

                <ul class="divide-y divide-mori-line/60">
                    <li>
                        <button
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm"
                            :class="
                                !filters.categoryId
                                    ? 'bg-mori-cream font-medium text-mori-ink'
                                    : 'text-mori-muted hover:bg-mori-cream hover:text-mori-ink'
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
                                    ? 'bg-mori-cream font-medium text-mori-ink'
                                    : 'text-mori-muted hover:bg-mori-cream hover:text-mori-ink'
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
                                class="rounded p-1 text-mori-muted hover:bg-[#fae9e9] hover:text-[#7a1f1f]"
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
                <div
                    class="admin-filter-bar flex flex-wrap items-end gap-3 rounded border border-mori-line bg-mori-paper p-4"
                >
                    <div class="min-w-48 flex-1 space-y-1.5">
                        <Label for="search">Search</Label>
                        <Input
                            id="search"
                            v-model="filters.search"
                            type="search"
                            placeholder="Dish name…"
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
                    class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
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
                            <span
                                class="text-xs tracking-wide text-mori-muted uppercase"
                            >
                                {{ categoryName(row) }}
                            </span>
                        </template>

                        <template #cell-course="{ row }">
                            <span
                                class="text-xs tracking-wide text-mori-muted uppercase"
                            >
                                {{ courseLabel(asItem(row).course) }}
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
                                        ? 'bg-[#ecf1ea] text-[#3f5c42]'
                                        : 'bg-mori-cream text-mori-muted'
                                "
                                :disabled="!canManageMenu"
                                :aria-label="`Toggle availability for ${row.name}`"
                                @click="toggleAvailability(asItem(row))"
                            >
                                {{
                                    row.is_available ? 'Available' : 'Sold out'
                                }}
                            </button>
                        </template>

                        <template #cell-is_featured="{ row }">
                            <span
                                class="text-xs"
                                :class="
                                    row.is_featured
                                        ? 'text-mori-wine'
                                        : 'text-mori-muted'
                                "
                            >
                                {{ row.is_featured ? 'Signature' : '—' }}
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <div
                                v-if="canManageMenu"
                                class="flex justify-end gap-1"
                            >
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
                                    class="rounded p-1.5 text-mori-muted hover:bg-[#fae9e9] hover:text-[#7a1f1f]"
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
                        class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
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
                        class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
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
                    <Input
                        id="category-name"
                        v-model="categoryForm.name"
                        maxlength="255"
                    />
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
            description="Update the dish details, ingredients, dietary information, and availability."
            wide
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1 sm:col-span-2">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        Dish details
                    </h2>
                    <p class="text-sm text-mori-muted">
                        Add the name, menu placement, price, and photo for this
                        dish.
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-name">Name</Label>
                    <Input
                        id="item-name"
                        v-model="itemForm.name"
                        maxlength="255"
                    />
                    <p v-if="fieldError('name')" class="text-xs text-[#7a1f1f]">
                        {{ fieldError('name') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-category">Category</Label>
                    <select
                        id="item-category"
                        v-model="itemForm.category_id"
                        class="mori-field h-9 w-full"
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
                    <p
                        v-if="fieldError('category_id')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('category_id') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-course">Course</Label>
                    <select
                        id="item-course"
                        v-model="itemForm.course"
                        class="mori-field h-9 w-full"
                    >
                        <option
                            v-for="course in menuCourses"
                            :key="course.value"
                            :value="course.value"
                        >
                            {{ course.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldError('course')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('course') }}
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
                    <p
                        v-if="fieldError('price')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('price') }}
                    </p>
                </div>
                <div class="space-y-1.5">
                    <Label for="item-image">Image URL</Label>
                    <Input
                        id="item-image"
                        v-model="itemForm.image_url"
                        type="url"
                        placeholder="Paste an image or enter a URL"
                        @paste="handleDishImagePaste"
                    />
                    <p class="text-sm text-mori-muted">
                        Paste a JPG, PNG, or WebP image here. It uploads when
                        you save the dish.
                    </p>
                    <div
                        v-if="pastedDishImagePreview || itemForm.image_url"
                        class="flex items-center gap-3 rounded border border-mori-line bg-mori-cream/50 p-2"
                    >
                        <img
                            :src="pastedDishImagePreview ?? itemForm.image_url"
                            alt="Dish image preview"
                            class="size-14 rounded object-cover"
                        />
                        <span class="text-sm text-mori-muted">
                            {{
                                pastedDishImage
                                    ? 'Ready to upload'
                                    : 'Current dish image'
                            }}
                        </span>
                    </div>
                    <p
                        v-if="fieldError('image_url')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('image_url') }}
                    </p>
                    <p
                        v-if="fieldError('image')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('image') }}
                    </p>
                </div>
                <fieldset class="space-y-2 sm:col-span-2">
                    <legend class="text-sm font-medium text-mori-ink">
                        Dietary tags
                    </legend>
                    <p class="text-sm leading-relaxed text-mori-muted">
                        Select a diet only when every ingredient fits, including
                        sauces and garnishes.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="tag in dietaryTags"
                            :key="tag.id"
                            class="flex items-center gap-2 text-sm text-mori-ink"
                            :title="tag.description ?? undefined"
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    itemForm.dietary_tags.includes(tag.slug)
                                "
                                @change="toggleDietaryTag(tag.slug)"
                            />
                            {{ tag.name }}
                        </label>
                    </div>
                    <p
                        v-if="fieldError('dietary_tags')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('dietary_tags') }}
                    </p>
                </fieldset>
                <section
                    class="space-y-3 border-t border-mori-line pt-4 sm:col-span-2"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-mori-ink">
                                Order options
                            </h2>
                            <p class="mt-1 text-sm text-mori-muted">
                                Let customers customize this dish. Extra prices
                                are added to each item.
                            </p>
                        </div>
                        <AppButton
                            variant="outline"
                            size="sm"
                            :icon="Plus"
                            @click="addCustomizationGroup"
                        >
                            Add option group
                        </AppButton>
                    </div>

                    <div
                        v-for="(
                            group, groupIndex
                        ) in itemForm.customization_groups"
                        :key="group.id"
                        class="space-y-3 rounded border border-mori-line bg-mori-cream/40 p-4"
                    >
                        <div
                            class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end"
                        >
                            <div class="space-y-1.5">
                                <Label :for="`option-group-${group.id}`"
                                    >Group name</Label
                                >
                                <Input
                                    :id="`option-group-${group.id}`"
                                    v-model="group.name"
                                    maxlength="100"
                                    placeholder="Choose a size"
                                />
                            </div>
                            <div class="space-y-1.5">
                                <Label :for="`option-mode-${group.id}`"
                                    >Selection style</Label
                                >
                                <select
                                    :id="`option-mode-${group.id}`"
                                    v-model="group.type"
                                    class="mori-field h-9 px-3"
                                >
                                    <option
                                        v-for="mode in customizationModes"
                                        :key="mode.value"
                                        :value="mode.value"
                                    >
                                        {{ mode.label }}
                                    </option>
                                </select>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-9 items-center justify-center rounded border border-mori-line text-mori-muted hover:bg-[#fae9e9] hover:text-[#7a1f1f]"
                                :aria-label="`Remove ${group.name || 'option group'}`"
                                @click="
                                    itemForm.customization_groups.splice(
                                        groupIndex,
                                        1,
                                    )
                                "
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="(option, optionIndex) in group.options"
                                :key="option.id"
                                class="grid gap-2 sm:grid-cols-[1fr_9rem_auto] sm:items-end"
                            >
                                <div class="space-y-1.5">
                                    <Label :for="`option-name-${option.id}`"
                                        >Option</Label
                                    >
                                    <Input
                                        :id="`option-name-${option.id}`"
                                        v-model="option.name"
                                        maxlength="100"
                                        placeholder="Large, extra sauce…"
                                    />
                                </div>
                                <div class="space-y-1.5">
                                    <Label :for="`option-price-${option.id}`"
                                        >Extra price (THB)</Label
                                    >
                                    <Input
                                        :id="`option-price-${option.id}`"
                                        v-model="option.price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                    />
                                </div>
                                <button
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded border border-mori-line text-mori-muted hover:bg-[#fae9e9] hover:text-[#7a1f1f]"
                                    :aria-label="`Remove ${option.name || 'option'}`"
                                    @click="
                                        group.options.splice(optionIndex, 1)
                                    "
                                >
                                    <Trash2 class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        </div>

                        <AppButton
                            variant="ghost"
                            size="sm"
                            :icon="Plus"
                            @click="addCustomizationOption(group)"
                        >
                            Add choice
                        </AppButton>
                        <p
                            v-if="!customizationGroupsValid"
                            class="text-xs text-mori-muted"
                        >
                            Each group and choice needs a name and a
                            non-negative price.
                        </p>
                    </div>
                </section>
                <div class="space-y-1.5 sm:col-span-2">
                    <Label for="item-description">Description</Label>
                    <Textarea
                        id="item-description"
                        v-model="itemForm.description"
                        rows="3"
                        maxlength="5000"
                    />
                </div>
                <div class="space-y-1.5 sm:col-span-2">
                    <Label for="item-ingredients">Ingredients</Label>
                    <Textarea
                        id="item-ingredients"
                        v-model="itemForm.ingredients"
                        rows="2"
                        maxlength="2000"
                        placeholder="Sauces, marinades and garnishes included…"
                    />
                    <p class="text-sm leading-relaxed text-mori-muted">
                        Customers can see this list. Leave it blank if the
                        ingredients have not been confirmed.
                    </p>
                </div>
                <fieldset class="space-y-2 sm:col-span-2">
                    <legend class="text-sm font-medium text-mori-ink">
                        Allergens in this recipe
                    </legend>
                    <p class="text-sm leading-relaxed text-mori-muted">
                        Select every allergen present, including in sauces and
                        marinades. Leave unchecked only when confirmed absent.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <label
                            v-for="allergen in allergenOptions"
                            :key="allergen.value"
                            class="flex items-center gap-2 text-sm text-mori-ink"
                        >
                            <input
                                type="checkbox"
                                :checked="
                                    itemForm.allergens.includes(allergen.value)
                                "
                                @change="toggleAllergen(allergen.value)"
                            />
                            {{ allergen.label }}
                        </label>
                    </div>
                    <p
                        v-if="fieldError('allergens')"
                        class="text-xs text-[#7a1f1f]"
                    >
                        {{ fieldError('allergens') }}
                    </p>
                </fieldset>
                <label class="flex items-center gap-2 text-sm text-mori-ink">
                    <input v-model="itemForm.is_available" type="checkbox" />
                    Available to order
                </label>
                <div
                    class="space-y-1.5 rounded border border-mori-line bg-mori-cream/40 p-3 sm:col-span-2"
                >
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm font-medium text-mori-ink"
                    >
                        <input v-model="itemForm.is_featured" type="checkbox" />
                        <Bookmark
                            class="size-4 text-mori-brass-ink"
                            :class="{ 'fill-mori-brass': itemForm.is_featured }"
                            aria-hidden="true"
                        />
                        Signature dish
                    </label>
                    <p class="pl-6 text-sm text-mori-muted">
                        Appears in the home page Signature list. Up to six
                        dishes can be featured; adding another replaces the
                        oldest.
                    </p>
                </div>
            </div>
            <template #footer>
                <AppButton variant="outline" @click="itemOpen = false">
                    Cancel
                </AppButton>
                <AppButton
                    :loading="itemSaving"
                    :disabled="
                        !itemForm.name.trim() ||
                        !itemForm.category_id ||
                        !customizationGroupsValid
                    "
                    @click="saveItem"
                >
                    Save dish
                </AppButton>
            </template>
        </AppModal>
    </div>
</template>
