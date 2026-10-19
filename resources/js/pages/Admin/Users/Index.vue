<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { LockKeyhole, Pencil, ShieldCheck } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AccountAccessController from '@/actions/App/Http/Controllers/Admin/AccountAccessController';
import DashboardController from '@/actions/App/Http/Controllers/Admin/DashboardController';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppButton from '@/components/mori/AppButton.vue';
import AppModal from '@/components/mori/AppModal.vue';
import DataTable from '@/components/mori/DataTable.vue';
import PlatformRoleBadge from '@/components/mori/PlatformRoleBadge.vue';
import { formatDate } from '@/lib/format';
import { userRoles } from '@/types/restaurant';
import type {
    Paginated,
    PlatformUser,
    StaffType,
    UserRole,
} from '@/types/restaurant';

type EditableRole = Exclude<UserRole, 'SUPER_ADMIN'>;

const props = defineProps<{
    canAssignRoles: boolean;
    filters: { search: string; role: string; page: number };
    users: Paginated<PlatformUser>;
}>();

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const rows = computed(
    () => props.users.data as unknown as Record<string, unknown>[],
);
const meta = computed(() => props.users.meta);
const editableRoles = userRoles.filter(
    (option): option is (typeof userRoles)[number] & { value: EditableRole } =>
        option.value !== 'SUPER_ADMIN',
);
const staffTypes: { value: StaffType; label: string }[] = [
    { value: 'CASHIER', label: 'Cashier' },
    { value: 'KITCHEN', label: 'Kitchen' },
    { value: 'WAITER', label: 'Waiter' },
    { value: 'DRIVER', label: 'Driver' },
];

const columns = [
    { key: 'name', label: 'Account' },
    { key: 'phone', label: 'Phone' },
    { key: 'role', label: 'Role' },
    { key: 'created_at', label: 'Joined' },
];

const filters = reactive({
    role: props.filters.role as UserRole | '',
    search: props.filters.search,
    page: props.filters.page,
});
const selectedUser = ref<PlatformUser | null>(null);
const dialogOpen = ref(false);
const confirmingRemoval = ref(false);
const saving = ref(false);
const fieldErrors = ref<Record<string, string>>({});
const editForm = reactive<{
    role: EditableRole;
    staff_type: StaffType | '';
}>({
    role: 'CUSTOMER',
    staff_type: '',
});

const hasChanges = computed(() => {
    if (selectedUser.value === null) {
        return false;
    }

    return (
        editForm.role !== selectedUser.value.role ||
        (editForm.role === 'STAFF' &&
            (editForm.staff_type || null) !== selectedUser.value.staff_type)
    );
});

watch(
    () => props.filters,
    (value) => {
        filters.search = value.search;
        filters.role = value.role as UserRole | '';
        filters.page = value.page;
    },
);

function asUser(row: Record<string, unknown>): PlatformUser {
    return row as unknown as PlatformUser;
}

function openEditor(user: PlatformUser): void {
    if (!props.canAssignRoles || user.role === 'SUPER_ADMIN') {
        return;
    }

    selectedUser.value = user;
    editForm.role = user.role;
    editForm.staff_type = user.staff_type ?? '';
    fieldErrors.value = {};
    confirmingRemoval.value = false;
    dialogOpen.value = true;
}

function closeEditor(): void {
    if (saving.value) {
        return;
    }

    dialogOpen.value = false;
    confirmingRemoval.value = false;
    selectedUser.value = null;
    fieldErrors.value = {};
}

function showSuccess(pageProps: Record<string, unknown>): void {
    const flash = pageProps.flash as { success?: string } | undefined;

    if (flash?.success) {
        toast.success(flash.success);
    }
}

function reloadUsers(): void {
    router.reload({ only: ['users'] });
}

function saveChanges(): void {
    const user = selectedUser.value;

    if (user === null || !hasChanges.value) {
        return;
    }

    saving.value = true;
    fieldErrors.value = {};

    const payload: { role: EditableRole; staff_type?: StaffType | null } = {
        role: editForm.role,
    };

    if (editForm.role === 'STAFF') {
        payload.staff_type = editForm.staff_type || null;
    }

    router.patch(
        AccountAccessController.updateRole.url({ user: user.id }),
        payload,
        {
            preserveScroll: true,
            onError: (errors) => {
                fieldErrors.value = errors;
            },
            onSuccess: (response) => {
                showSuccess(response.props);
                dialogOpen.value = false;
                selectedUser.value = null;
                reloadUsers();
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function removeAccess(): void {
    const user = selectedUser.value;

    if (user === null) {
        return;
    }

    saving.value = true;
    fieldErrors.value = {};

    router.post(
        AccountAccessController.removeAccess.url({ user: user.id }),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                fieldErrors.value = errors;
            },
            onSuccess: (response) => {
                showSuccess(response.props);
                dialogOpen.value = false;
                selectedUser.value = null;
                confirmingRemoval.value = false;
                reloadUsers();
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function visitUsers(pageNumber: number): void {
    filters.page = pageNumber;

    router.get(
        DashboardController.users.url({
            query: {
                search: filters.search.trim() || undefined,
                role: filters.role || undefined,
                page: pageNumber,
            },
        }),
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function applyFilters(): void {
    visitUsers(1);
}
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
                Admin can assign roles to non-Super Admin accounts. Super Admin
                accounts cannot be changed.
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
                    class="mori-field h-12 px-3"
                />
            </div>

            <div class="min-w-40 space-y-1.5">
                <label for="role" class="mori-field-label"> Role </label>
                <select
                    id="role"
                    v-model="filters.role"
                    class="mori-field h-12 px-3"
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

        <div class="mt-4">
            <DataTable
                :columns="columns"
                :rows="rows"
                row-key="id"
                striped
                empty-title="No accounts"
                empty-description="No account matches these filters."
            >
                <template #cell-name="{ row }">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-col">
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
                        <button
                            v-if="asUser(row).role !== 'SUPER_ADMIN'"
                            type="button"
                            class="shrink-0 rounded p-1 text-mori-muted hover:bg-mori-cream hover:text-mori-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine disabled:cursor-not-allowed disabled:opacity-50"
                            :aria-label="`Edit role for ${asUser(row).name}`"
                            :disabled="!props.canAssignRoles"
                            @click.stop="openEditor(asUser(row))"
                        >
                            <Pencil class="size-4" aria-hidden="true" />
                        </button>
                        <span
                            v-else
                            class="shrink-0 rounded p-1 text-mori-muted"
                            title="Super Admin accounts cannot be changed"
                            aria-label="Super Admin accounts cannot be changed"
                        >
                            <LockKeyhole class="size-4" aria-hidden="true" />
                        </span>
                    </div>
                </template>

                <template #cell-phone="{ row }">
                    <span class="text-mori-muted">
                        {{ asUser(row).phone ?? '—' }}
                    </span>
                </template>

                <template #cell-role="{ row }">
                    <PlatformRoleBadge :role="asUser(row).role" />
                </template>

                <template #cell-created_at="{ row }">
                    <span class="text-mori-muted">
                        {{ formatDate(asUser(row).created_at) }}
                    </span>
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
                @click="visitUsers(meta.current_page - 1)"
            >
                Previous
            </button>
            <button
                type="button"
                class="rounded border border-mori-field bg-mori-paper px-3 py-1.5 text-mori-ink hover:bg-mori-cream disabled:cursor-not-allowed disabled:border-mori-line disabled:bg-[#ede9e1] disabled:text-mori-muted"
                :disabled="meta.current_page >= meta.last_page"
                @click="visitUsers(meta.current_page + 1)"
            >
                Next
            </button>
        </div>

        <AppModal
            v-model:open="dialogOpen"
            title="Edit account role"
            description="Update this account's platform role and staff type."
        >
            <div v-if="selectedUser" class="space-y-5">
                <div class="space-y-1">
                    <p class="font-medium text-mori-ink">
                        {{ selectedUser.name }}
                    </p>
                    <p class="text-sm text-mori-muted">
                        {{ selectedUser.email }}
                    </p>
                </div>

                <div class="space-y-2">
                    <label for="account-role" class="mori-field-label">
                        Role
                    </label>
                    <select
                        id="account-role"
                        v-model="editForm.role"
                        class="mori-field h-12 bg-white px-3"
                        :aria-invalid="Boolean(fieldErrors.role)"
                        :disabled="saving"
                    >
                        <option
                            v-for="option in editableRoles"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.role"
                        class="text-sm text-[#7a1f1f]"
                        role="alert"
                    >
                        {{ fieldErrors.role }}
                    </p>
                </div>

                <div v-if="editForm.role === 'STAFF'" class="space-y-2">
                    <label for="account-staff-type" class="mori-field-label">
                        Staff type
                    </label>
                    <select
                        id="account-staff-type"
                        v-model="editForm.staff_type"
                        class="mori-field h-12 bg-white px-3"
                        :aria-invalid="Boolean(fieldErrors.staff_type)"
                        :disabled="saving"
                    >
                        <option value="">No specific type</option>
                        <option
                            v-for="staffType in staffTypes"
                            :key="staffType.value"
                            :value="staffType.value"
                        >
                            {{ staffType.label }}
                        </option>
                    </select>
                    <p
                        v-if="fieldErrors.staff_type"
                        class="text-sm text-[#7a1f1f]"
                        role="alert"
                    >
                        {{ fieldErrors.staff_type }}
                    </p>
                </div>

                <p
                    v-if="confirmingRemoval"
                    class="rounded border border-[#e7c5c5] bg-[#fae9e9] p-3 text-sm text-[#7a1f1f]"
                    role="alert"
                >
                    This will make {{ selectedUser.name }} a Customer and remove
                    their restaurant access. Continue?
                </p>
            </div>

            <template #footer>
                <template v-if="confirmingRemoval">
                    <AppButton
                        variant="outline"
                        :disabled="saving"
                        @click="confirmingRemoval = false"
                    >
                        Cancel
                    </AppButton>
                    <AppButton
                        variant="outline"
                        class="border-[#8c2f2f] text-[#7a1f1f] hover:bg-[#fae9e9]"
                        :loading="saving"
                        @click="removeAccess"
                    >
                        Confirm remove access
                    </AppButton>
                </template>
                <template v-else>
                    <AppButton
                        variant="outline"
                        :disabled="saving"
                        @click="closeEditor"
                    >
                        Cancel
                    </AppButton>
                    <AppButton
                        v-if="
                            selectedUser &&
                            ['STAFF', 'ADMIN'].includes(selectedUser.role)
                        "
                        variant="outline"
                        class="border-[#8c2f2f] text-[#7a1f1f] hover:bg-[#fae9e9]"
                        :disabled="saving"
                        @click="confirmingRemoval = true"
                    >
                        Remove access
                    </AppButton>
                    <AppButton
                        :loading="saving"
                        :disabled="!hasChanges"
                        @click="saveChanges"
                    >
                        Save changes
                    </AppButton>
                </template>
            </template>
        </AppModal>
    </div>
</template>
