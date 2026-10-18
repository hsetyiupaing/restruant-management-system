<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppBadge from '@/components/mori/AppBadge.vue';
import AppButton from '@/components/mori/AppButton.vue';
import DataTable from '@/components/mori/DataTable.vue';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { userRoleLabel, userRoles } from '@/types/restaurant';
import type { Paginated, PlatformUser, UserRole } from '@/types/restaurant';

const props = defineProps<{
    canAssignRoles: boolean;
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const users = ref<PlatformUser[]>([]);
const meta = ref<Paginated<PlatformUser>['meta'] | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);
const savingId = ref<number | null>(null);

const filters = reactive({
    role: '' as UserRole | '',
    search: '',
    page: 1,
});

const columns = [
    { key: 'name', label: 'Account' },
    { key: 'phone', label: 'Phone' },
    { key: 'role', label: 'Role' },
    { key: 'created_at', label: 'Joined' },
    { key: 'actions', label: '', align: 'right' as const },
];

const rows = computed(
    () => users.value as unknown as Record<string, unknown>[],
);

function asUser(row: Record<string, unknown>): PlatformUser {
    return row as unknown as PlatformUser;
}

const roleTones: Record<UserRole, 'wine' | 'sage' | 'sand' | 'neutral'> = {
    SUPER_ADMIN: 'wine',
    ADMIN: 'sage',
    STAFF: 'sand',
    CUSTOMER: 'neutral',
};

function roleTone(role: UserRole): 'wine' | 'sage' | 'sand' | 'neutral' {
    return roleTones[role] ?? 'neutral';
}

/** Only another Super Admin can hand out privileged roles. */
async function changeRole(user: PlatformUser, event: Event): Promise<void> {
    const select = event.target as HTMLSelectElement;
    const role = select.value as UserRole;

    if (role === user.role) {
        return;
    }

    savingId.value = user.id;

    try {
        const updated = await api.patch<{ data: PlatformUser }>(
            `/admin/users/${user.id}/role`,
            { role },
        );

        users.value = users.value.map((entry) =>
            entry.id === updated.data.id ? updated.data : entry,
        );
        toast.success(`${updated.data.name} is now ${userRoleLabel(role)}.`);
    } catch (caught) {
        // Put the control back where it was so the UI never claims a change
        // the server refused (e.g. the last Super Admin).
        select.value = user.role;

        if (caught instanceof ApiError && caught.status === 403) {
            toast.error('Only a Super Admin can change account roles.');
        } else {
            toast.error(errorMessage(caught));
        }
    } finally {
        savingId.value = null;
    }
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<Paginated<PlatformUser>>(
            '/admin/users',
            {
                query: {
                    role: filters.role || undefined,
                    search: filters.search.trim() || undefined,
                    page: filters.page,
                    per_page: 20,
                },
            },
        );

        users.value = response.data;
        meta.value = response.meta;
    } catch (caught) {
        if (caught instanceof ApiError && caught.status === 403) {
            error.value = 'Only a Super Admin can manage platform accounts.';
        } else {
            error.value = errorMessage(caught);
        }
    } finally {
        loading.value = false;
    }
}

function applyFilters(): void {
    filters.page = 1;
    void load();
}

onMounted(() => void load());
</script>

<template>
    <Head title="Accounts · MORI Admin" />

    <AdminTopbar title="Platform accounts" subtitle="Roles and access" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <div
            class="flex max-w-3xl items-start gap-3 rounded border border-mori-line bg-mori-cream/60 px-4 py-3 text-sm text-mori-ink"
        >
            <ShieldCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <p>
                Public registration always creates a Customer. Only a Super
                Admin can raise an account to Staff, Administrator or Super
                Admin, and the last Super Admin cannot be demoted.
            </p>
        </div>

        <form
            class="admin-filter-bar mt-4 flex flex-wrap items-end gap-3 rounded border border-mori-line bg-mori-paper p-4"
            @submit.prevent="applyFilters"
        >
            <div class="min-w-48 flex-1 space-y-1.5">
                <label for="search" class="mori-field-label"> Search </label>
                <input
                    id="search"
                    v-model="filters.search"
                    type="search"
                    placeholder="Name or email"
                    class="mori-field h-9 px-3"
                />
            </div>

            <div class="min-w-40 space-y-1.5">
                <label for="role" class="mori-field-label"> Role </label>
                <select
                    id="role"
                    v-model="filters.role"
                    class="mori-field h-9 px-3"
                >
                    <option value="">All roles</option>
                    <option
                        v-for="option in userRoles"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>

            <AppButton type="submit" variant="dark" size="sm">
                Apply
            </AppButton>
        </form>

        <p
            v-if="error"
            class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-else class="mt-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                row-key="id"
                :loading="loading"
                striped
                empty-title="No accounts"
                empty-description="No account matches these filters."
            >
                <template #cell-name="{ row }">
                    <div class="flex flex-col">
                        <span class="font-medium text-mori-ink">
                            {{ asUser(row).name }}
                            <span
                                v-if="asUser(row).id === currentUserId"
                                class="ml-1 text-xs font-normal text-mori-muted"
                            >
                                (you)
                            </span>
                        </span>
                        <span class="text-xs text-mori-muted">
                            {{ asUser(row).email }}
                        </span>
                    </div>
                </template>

                <template #cell-phone="{ row }">
                    <span class="text-mori-muted">
                        {{ asUser(row).phone ?? '—' }}
                    </span>
                </template>

                <template #cell-role="{ row }">
                    <AppBadge :tone="roleTone(asUser(row).role)">
                        {{ userRoleLabel(asUser(row).role) }}
                    </AppBadge>
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-mori-muted">
                        {{ formatDate(asUser(row).created_at) }}
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex justify-end">
                        <label class="sr-only" :for="`role-${asUser(row).id}`">
                            Change role for {{ asUser(row).name }}
                        </label>
                        <select
                            :id="`role-${asUser(row).id}`"
                            :value="asUser(row).role"
                            :disabled="
                                !props.canAssignRoles ||
                                savingId === asUser(row).id ||
                                asUser(row).id === currentUserId
                            "
                            class="mori-field h-9 px-2 disabled:cursor-not-allowed"
                            @change="changeRole(asUser(row), $event)"
                        >
                            <option
                                v-for="option in userRoles"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </template>
            </DataTable>

            <p
                v-if="meta && meta.last_page > 1"
                class="mt-3 text-xs text-mori-muted"
            >
                Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of
                {{ meta.total }}
            </p>
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
                    load();
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
                    load();
                "
            >
                Next
            </button>
        </div>
    </div>
</template>
