<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import DataTable from '@/components/mori/DataTable.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { api, errorMessage } from '@/lib/api';
import { formatDate, statusLabel } from '@/lib/format';
import type { Paginated, RestaurantMember } from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const members = ref<RestaurantMember[]>([]);
const meta = ref<Paginated<RestaurantMember>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const forbidden = ref(false);

const filters = ref({
    role: '' as 'STAFF' | 'ADMIN' | '',
    page: 1,
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'staff_type', label: 'Role detail' },
    { key: 'created_at', label: 'Joined', align: 'right' as const },
];

const rows = computed(
    () => members.value as unknown as Record<string, unknown>[],
);

function memberUser(row: Record<string, unknown>): {
    name?: string;
    email?: string;
} {
    return (row.user as { name?: string; email?: string } | undefined) ?? {};
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;
    forbidden.value = false;

    try {
        const response = await api.get<Paginated<RestaurantMember>>(
            `/admin/restaurants/${props.restaurant.id}/members`,
            {
                query: {
                    role: filters.value.role || undefined,
                    page: filters.value.page,
                    per_page: 20,
                },
            },
        );

        members.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        if (
            caught instanceof Error &&
            'status' in caught &&
            caught.status === 403
        ) {
            forbidden.value = true;
        } else {
            error.value = errorMessage(caught);
        }
    } finally {
        loading.value = false;
    }
}

watch(
    () => filters.value.role,
    () => {
        filters.value.page = 1;
        void load();
    },
);

watch(
    () => filters.value.page,
    () => void load(),
);

onMounted(() => void load());
</script>

<template>
    <Head title="Team · MORI Admin" />

    <AdminTopbar title="Team & customers" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <p class="max-w-2xl text-sm text-mori-muted">
            The v1 API exposes the people who work at this restaurant. Customer
            account profiles live with the platform, so they are not listed
            here.
        </p>

        <div
            class="admin-filter-bar mt-4 flex flex-wrap items-end gap-3 rounded border border-mori-line bg-mori-paper p-4"
        >
            <div class="min-w-40 space-y-1.5">
                <label for="role" class="mori-field-label"> Role </label>
                <select
                    id="role"
                    v-model="filters.role"
                    class="mori-field h-9 w-full"
                >
                    <option value="">All roles</option>
                    <option value="ADMIN">Administrator</option>
                    <option value="STAFF">Staff</option>
                </select>
            </div>
        </div>

        <p
            v-if="forbidden"
            class="mt-4 rounded border border-[#e8d3a3] bg-[#fbf0d9] px-4 py-3 text-sm text-[#6b4a12]"
            role="status"
        >
            Only restaurant administrators can view the team roster.
        </p>

        <p
            v-else-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="!forbidden" class="mt-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                row-key="id"
                :loading="loading"
                striped
                empty-title="No team members"
                empty-description="Invite staff to help run this restaurant."
            >
                <template #cell-name="{ row }">
                    <span class="font-medium text-mori-ink">
                        {{ memberUser(row).name ?? '—' }}
                    </span>
                </template>

                <template #cell-email="{ row }">
                    <span class="text-mori-muted">
                        {{ memberUser(row).email ?? '—' }}
                    </span>
                </template>

                <template #cell-role="{ row }">
                    <StatusBadge
                        :status="row.role === 'ADMIN' ? 'CONFIRMED' : 'PENDING'"
                    />
                    <span class="ml-2 text-xs text-mori-muted">
                        {{ row.role === 'ADMIN' ? 'Administrator' : 'Staff' }}
                    </span>
                </template>

                <template #cell-staff_type="{ row }">
                    <span class="text-mori-muted">
                        {{
                            row.staff_type
                                ? statusLabel(String(row.staff_type))
                                : '—'
                        }}
                    </span>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-mori-muted">
                        {{ formatDate(String(row.created_at)) }}
                    </span>
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
                @click="filters.page = meta.current_page - 1"
            >
                Previous
            </button>
            <button
                type="button"
                class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
                :disabled="meta.current_page >= meta.last_page"
                @click="filters.page = meta.current_page + 1"
            >
                Next
            </button>
        </div>
    </div>
</template>
