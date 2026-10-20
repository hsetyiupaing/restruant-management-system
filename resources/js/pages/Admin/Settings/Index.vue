<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AdminTopbar from '@/components/admin/AdminTopbar.vue';
import AppButton from '@/components/mori/AppButton.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ApiError, api, errorMessage } from '@/lib/api';
import type { Restaurant } from '@/types/restaurant';

const props = defineProps<{
    restaurant: { id: number; name: string; slug: string };
    canManageMenu: boolean;
}>();

const days = [
    ['mon', 'Monday'],
    ['tue', 'Tuesday'],
    ['wed', 'Wednesday'],
    ['thu', 'Thursday'],
    ['fri', 'Friday'],
    ['sat', 'Saturday'],
    ['sun', 'Sunday'],
] as const;

const loading = ref(true);
const saving = ref(false);
const error = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const isActive = ref(true);

const form = reactive({
    name: '',
    phone: '',
    address: '',
    slot_minutes: 60,
    auto_confirm_reservations: false,
    delivery_fee: '0',
    min_delivery_amount: '0',
    tax_rate: '0',
});

const hours = reactive<
    Record<string, { open: string; close: string; closed: boolean }>
>(
    Object.fromEntries(
        days.map(([key]) => [
            key,
            { open: '11:00', close: '22:00', closed: false },
        ]),
    ),
);

function fieldError(field: string): string | null {
    return fieldErrors.value[field]?.[0] ?? null;
}

async function load(): Promise<void> {
    loading.value = true;
    error.value = null;

    try {
        const response = await api.get<{ data: Restaurant }>(
            `/admin/restaurants/${props.restaurant.id}/settings`,
        );

        const restaurant = response.data;

        form.name = restaurant.name;
        form.phone = restaurant.phone ?? '';
        form.address = restaurant.address ?? '';
        form.slot_minutes = restaurant.settings.slot_minutes;
        form.auto_confirm_reservations =
            restaurant.settings.auto_confirm_reservations;
        form.delivery_fee = restaurant.settings.delivery_fee;
        form.min_delivery_amount = restaurant.settings.min_delivery_amount;
        form.tax_rate = restaurant.settings.tax_rate;
        isActive.value = restaurant.is_active;

        if (restaurant.opening_hours) {
            days.forEach(([key]) => {
                const window = restaurant.opening_hours?.[key];

                hours[key] = {
                    open: window?.open ?? '11:00',
                    close: window?.close ?? '22:00',
                    closed: window === null || window === undefined,
                };
            });
        }
    } catch (caught) {
        error.value = errorMessage(caught);
    } finally {
        loading.value = false;
    }
}

async function save(): Promise<void> {
    saving.value = true;
    fieldErrors.value = {};

    const openingHours = Object.fromEntries(
        days.map(([key]) => [
            key,
            hours[key].closed
                ? null
                : { open: hours[key].open, close: hours[key].close },
        ]),
    );

    try {
        await api.patch(`/admin/restaurants/${props.restaurant.id}`, {
            name: form.name.trim(),
            phone: form.phone.trim() || null,
            address: form.address.trim() || null,
            slot_minutes: form.slot_minutes,
            auto_confirm_reservations: form.auto_confirm_reservations,
            delivery_fee: Number.parseFloat(form.delivery_fee || '0'),
            min_delivery_amount: Number.parseFloat(
                form.min_delivery_amount || '0',
            ),
            tax_rate: Number.parseFloat(form.tax_rate || '0'),
            opening_hours: openingHours,
        });

        toast.success('Settings saved.');
    } catch (caught) {
        if (caught instanceof ApiError) {
            fieldErrors.value = caught.errors;
        }

        toast.error(errorMessage(caught));
    } finally {
        saving.value = false;
    }
}

onMounted(() => void load());
</script>

<template>
    <Head title="Settings · MORI Admin" />

    <AdminTopbar title="Settings" :subtitle="restaurant.name" />

    <div class="flex-1 px-5 py-6 lg:px-8">
        <p
            v-if="error"
            class="rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
            role="alert"
        >
            {{ error }}
        </p>

        <div v-if="loading" class="space-y-4" aria-busy="true">
            <div
                class="h-64 animate-pulse rounded-lg border border-mori-line bg-mori-paper"
            />
            <div
                class="h-48 animate-pulse rounded-lg border border-mori-line bg-mori-paper"
            />
        </div>

        <EmptyState
            v-else-if="error"
            title="Settings unavailable"
            description="You do not have access to this restaurant's settings."
        />

        <form v-else class="grid gap-4 lg:grid-cols-2" @submit.prevent="save">
            <section class="report-card p-5">
                <h2 class="report-card-title">Profile</h2>

                <div class="mt-4 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="name">Restaurant name</Label>
                        <Input id="name" v-model="form.name" maxlength="255" />
                        <p
                            v-if="fieldError('name')"
                            class="text-xs text-[#7a1f1f]"
                        >
                            {{ fieldError('name') }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            v-model="form.phone"
                            type="tel"
                            maxlength="32"
                        />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="address">Address</Label>
                        <Input
                            id="address"
                            v-model="form.address"
                            maxlength="255"
                        />
                    </div>
                    <p class="text-xs text-mori-muted">
                        Storefront URL: /{{ restaurant.slug }} ·
                        <span
                            :class="
                                isActive ? 'text-mori-sage' : 'text-mori-wine'
                            "
                        >
                            {{ isActive ? 'active' : 'inactive' }}
                        </span>
                    </p>
                </div>
            </section>

            <section class="report-card p-5">
                <h2 class="report-card-title">Orders & reservations</h2>

                <div class="mt-4 space-y-4">
                    <div class="space-y-1.5">
                        <Label for="slot_minutes"
                            >Reservation slot (minutes)</Label
                        >
                        <Input
                            id="slot_minutes"
                            v-model.number="form.slot_minutes"
                            type="number"
                            min="15"
                            max="480"
                            step="15"
                        />
                        <p
                            v-if="fieldError('slot_minutes')"
                            class="text-xs text-[#7a1f1f]"
                        >
                            {{ fieldError('slot_minutes') }}
                        </p>
                    </div>

                    <label
                        class="flex items-center gap-2 text-sm text-mori-ink"
                    >
                        <input
                            v-model="form.auto_confirm_reservations"
                            type="checkbox"
                        />
                        Confirm reservations automatically
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <Label for="delivery_fee">Delivery fee (THB)</Label>
                            <Input
                                id="delivery_fee"
                                v-model="form.delivery_fee"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <Label for="min_delivery_amount">
                                Minimum delivery (THB)
                            </Label>
                            <Input
                                id="min_delivery_amount"
                                v-model="form.min_delivery_amount"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="tax_rate">Tax rate (0–1)</Label>
                        <Input
                            id="tax_rate"
                            v-model="form.tax_rate"
                            type="number"
                            step="0.0001"
                            min="0"
                            max="1"
                        />
                        <p class="text-xs text-mori-muted">
                            0.07 means 7% VAT, applied by the server to the
                            subtotal.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded border border-mori-line bg-mori-paper p-5 lg:col-span-2"
            >
                <h2 class="report-card-title">Opening hours</h2>
                <p class="mt-1 text-xs text-mori-muted">
                    Reservations outside these hours are refused by the API.
                </p>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="[key, label] in days"
                        :key="key"
                        class="space-y-1.5 rounded border border-mori-line p-3"
                    >
                        <p class="mori-field-label">
                            {{ label }}
                        </p>
                        <div class="flex items-center gap-1.5">
                            <Input
                                v-model="hours[key].open"
                                type="time"
                                class="h-8 min-w-0 flex-1 text-xs"
                                :disabled="hours[key].closed"
                                :aria-label="`${label} opening time`"
                            />
                            <span class="text-xs text-mori-muted">–</span>
                            <Input
                                v-model="hours[key].close"
                                type="time"
                                class="h-8 min-w-0 flex-1 text-xs"
                                :disabled="hours[key].closed"
                                :aria-label="`${label} closing time`"
                            />
                        </div>
                        <label
                            class="flex items-center gap-1.5 text-xs text-mori-muted"
                        >
                            <input
                                v-model="hours[key].closed"
                                type="checkbox"
                            />
                            Closed
                        </label>
                    </div>
                </div>
            </section>

            <div class="flex justify-end lg:col-span-2">
                <AppButton type="submit" :loading="saving" size="lg">
                    Save settings
                </AppButton>
            </div>
        </form>
    </div>
</template>
