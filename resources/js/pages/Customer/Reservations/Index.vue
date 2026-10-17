<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppButton from '@/components/mori/AppButton.vue';
import SectionHeading from '@/components/mori/SectionHeading.vue';
import StatusBadge from '@/components/mori/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatDate, formatTime, statusLabel } from '@/lib/format';
import type {
    Availability,
    DiningTable,
    Paginated,
    Reservation,
    ReservationStatus,
    Restaurant,
} from '@/types/restaurant';

const props = defineProps<{ restaurant: Restaurant | null }>();

const bookings = ref<Reservation[]>([]);
const bookingsLoading = ref(true);
const bookingsError = ref<string | null>(null);

const availability = ref<Availability['data'] | null>(null);
const availabilityLoading = ref(false);
const availabilityError = ref<string | null>(null);

const selectedTableId = ref<number | null>(null);
const submitting = ref(false);
const fieldErrors = ref<Record<string, string[]>>({});

const form = reactive({
    date: todayIso(),
    time: '19:00',
    party_size: 2,
    special_request: '',
});

const slots = [
    '11:30', '12:00', '12:30', '13:00', '13:30', '14:00',
    '17:30', '18:00', '18:30', '19:00', '19:30', '20:00',
    '20:30', '21:00', '21:30',
];

const selectedTable = computed<DiningTable | null>(
    () =>
        availability.value?.tables.find(
            (table) => table.id === selectedTableId.value,
        ) ?? null,
);

const canBook = computed(
    () =>
        props.restaurant !== null &&
        selectedTable.value !== null &&
        selectedTable.value.status === 'AVAILABLE' &&
        !submitting.value,
);

const upcomingStatuses: ReservationStatus[] = ['PENDING', 'CONFIRMED', 'SEATED'];

function todayIso(): string {
    // Reservation times are wall-clock values with no meaningful timezone, so
    // the UTC components are the ones the customer means.
    return new Date().toISOString().slice(0, 10);
}

function errorFor(field: string): string | null {
    return fieldErrors.value[field]?.[0] ?? null;
}

async function loadBookings(): Promise<void> {
    bookingsLoading.value = true;
    bookingsError.value = null;

    try {
        const response = await api.get<Paginated<Reservation>>('/reservations', {
            query: { per_page: 10 },
        });

        bookings.value = response.data;
    } catch (caught) {
        bookingsError.value = errorMessage(caught);
    } finally {
        bookingsLoading.value = false;
    }
}

async function loadAvailability(): Promise<void> {
    if (!props.restaurant) {
        return;
    }

    availabilityLoading.value = true;
    availabilityError.value = null;
    selectedTableId.value = null;

    try {
        availability.value = (
            await api.get<Availability>(
                `/restaurants/${props.restaurant.slug}/availability`,
                {
                    query: {
                        date: form.date,
                        time: form.time,
                        party_size: form.party_size,
                    },
                },
            )
        ).data;
    } catch (caught) {
        availability.value = null;
        availabilityError.value = errorMessage(caught);
    } finally {
        availabilityLoading.value = false;
    }
}

async function book(): Promise<void> {
    if (!canBook.value || !props.restaurant || !selectedTable.value) {
        return;
    }

    submitting.value = true;
    fieldErrors.value = {};

    try {
        const response = await api.post<{ data: Reservation }>('/reservations', {
            restaurant_id: props.restaurant.id,
            table_id: selectedTable.value.id,
            party_size: form.party_size,
            // Wall-clock time expressed as UTC so it round-trips unchanged.
            start_time: `${form.date}T${form.time}:00Z`,
            special_request: form.special_request.trim() || null,
        });

        bookings.value = [response.data, ...bookings.value];
        form.special_request = '';
        toast.success(
            response.data.status === 'CONFIRMED'
                ? 'Table confirmed.'
                : 'Table held — the restaurant will confirm shortly.',
        );
        await loadAvailability();
    } catch (caught) {
        if (caught instanceof ApiError) {
            fieldErrors.value = caught.errors;
        }

        toast.error(errorMessage(caught));
    } finally {
        submitting.value = false;
    }
}

const cancelling = ref<number | null>(null);

async function cancel(reservation: Reservation): Promise<void> {
    cancelling.value = reservation.id;

    try {
        const response = await api.post<{ data: Reservation }>(
            `/reservations/${reservation.id}/cancel`,
        );

        bookings.value = bookings.value.map((entry) =>
            entry.id === reservation.id ? response.data : entry,
        );
        toast.success('Reservation cancelled.');
        await loadAvailability();
    } catch (caught) {
        toast.error(errorMessage(caught));
    } finally {
        cancelling.value = null;
    }
}

function tablePosition(table: DiningTable): Record<string, string> {
    return {
        left: `${(table.pos_x / 800) * 100}%`,
        top: `${(table.pos_y / 600) * 100}%`,
    };
}

function tableStatus(table: DiningTable): string {
    return table.status ?? 'AVAILABLE';
}

function tableClass(table: DiningTable): string {
    if (table.status === 'OCCUPIED') {
        return 'bg-mori-ink text-white';
    }

    if (table.status === 'RESERVED' || table.status === 'UNAVAILABLE') {
        return 'bg-mori-cream text-mori-muted';
    }

    return 'bg-mori-sage/15 text-mori-sage hover:bg-mori-sage/25';
}

watch(
    () => [form.date, form.time, form.party_size],
    () => void loadAvailability(),
);

onMounted(() => {
    void loadBookings();
    void loadAvailability();
});
</script>

<template>
    <Head title="Reserve a table · MORI" />

    <div class="mori-container py-12">
        <SectionHeading
            eyebrow="Reservations"
            title="Book a table"
            text="Choose a time, then pick your spot on the floor plan. Tables are held for 15 minutes after booking."
        />

        <p
            v-if="!restaurant"
            class="mt-6 rounded border border-mori-line bg-mori-cream px-4 py-3 text-sm text-mori-muted"
        >
            Online reservations are unavailable because no restaurant is active.
        </p>

        <div v-else class="mt-8 grid gap-8 lg:grid-cols-[380px_1fr]">
            <div class="space-y-6">
                <section class="rounded border border-mori-line bg-mori-paper p-5">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        When are you coming?
                    </h2>

                    <div class="mt-4 space-y-4">
                        <div class="space-y-1.5">
                            <Label for="date">Date</Label>
                            <Input
                                id="date"
                                v-model="form.date"
                                type="date"
                                :min="todayIso()"
                                :aria-invalid="Boolean(errorFor('start_time'))"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="time">Time</Label>
                                <select
                                    id="time"
                                    v-model="form.time"
                                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                                >
                                    <option
                                        v-for="slot in slots"
                                        :key="slot"
                                        :value="slot"
                                    >
                                        {{ slot }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="party_size">Guests</Label>
                                <Input
                                    id="party_size"
                                    v-model.number="form.party_size"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :aria-invalid="Boolean(errorFor('party_size'))"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="special_request">Anything we should know?</Label>
                            <Textarea
                                id="special_request"
                                v-model="form.special_request"
                                rows="2"
                                maxlength="1000"
                                placeholder="Birthday, allergies, high chair…"
                            />
                        </div>

                        <p
                            v-if="errorFor('start_time') || errorFor('table_id')"
                            class="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                            role="alert"
                        >
                            {{ errorFor('start_time') ?? errorFor('table_id') }}
                        </p>

                        <AppButton
                            block
                            size="lg"
                            :loading="submitting"
                            :disabled="!canBook"
                            @click="book"
                        >
                            {{
                                selectedTable
                                    ? `Reserve table ${selectedTable.label}`
                                    : 'Select a table'
                            }}
                        </AppButton>
                    </div>
                </section>

                <section class="rounded border border-mori-line bg-mori-paper">
                    <h2 class="border-b border-mori-line px-5 py-3 text-sm font-semibold text-mori-ink">
                        Your reservations
                    </h2>

                    <p
                        v-if="bookingsError"
                        class="px-5 py-4 text-sm text-red-800"
                        role="alert"
                    >
                        {{ bookingsError }}
                    </p>

                    <div
                        v-else-if="bookingsLoading"
                        class="space-y-3 px-5 py-4"
                        aria-busy="true"
                    >
                        <div
                            v-for="index in 2"
                            :key="index"
                            class="h-16 animate-pulse rounded bg-mori-cream/70"
                        />
                    </div>

                    <ul v-else-if="bookings.length" class="divide-y divide-mori-line/70">
                        <li
                            v-for="reservation in bookings"
                            :key="reservation.id"
                            class="px-5 py-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-mori-ink">
                                        {{ formatDate(reservation.start_time) }} ·
                                        {{ formatTime(reservation.start_time) }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-mori-muted">
                                        {{ reservation.party_size }} guests
                                        <template v-if="reservation.table">
                                            · table {{ reservation.table.label }}
                                        </template>
                                    </p>
                                </div>
                                <StatusBadge :status="reservation.status" />
                            </div>
                            <AppButton
                                v-if="upcomingStatuses.includes(reservation.status)"
                                variant="ghost"
                                size="sm"
                                class="mt-2 px-0"
                                :loading="cancelling === reservation.id"
                                @click="cancel(reservation)"
                            >
                                Cancel
                            </AppButton>
                        </li>
                    </ul>

                    <p v-else class="px-5 py-6 text-sm text-mori-muted">
                        No reservations yet.
                    </p>
                </section>
            </div>

            <section class="rounded border border-mori-line bg-mori-paper p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold text-mori-ink">
                        Floor plan
                    </h2>
                    <ul class="flex flex-wrap gap-3 text-xs text-mori-muted">
                        <li class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-mori-sage/40" />
                            Available
                        </li>
                        <li class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-mori-cream ring-1 ring-mori-line" />
                            Taken
                        </li>
                        <li class="flex items-center gap-1.5">
                            <span class="size-2.5 rounded-full bg-mori-ink" />
                            Seated
                        </li>
                    </ul>
                </div>

                <p
                    v-if="availabilityError"
                    class="mt-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800"
                    role="alert"
                >
                    {{ availabilityError }}
                </p>

                <div
                    v-else
                    class="relative mt-4 aspect-[4/3] w-full overflow-hidden rounded border border-mori-line bg-mori-cream/40"
                    :aria-busy="availabilityLoading"
                >
                    <div
                        v-if="availabilityLoading"
                        class="flex h-full items-center justify-center text-sm text-mori-muted"
                    >
                        Loading floor plan…
                    </div>

                    <template v-else-if="availability">
                        <button
                            v-for="table in availability.tables"
                            :key="table.id"
                            type="button"
                            class="absolute -translate-x-1/2 -translate-y-1/2 border text-xs font-medium transition-colors"
                            :class="[
                                tableClass(table),
                                table.shape === 'ROUND'
                                    ? 'size-14 rounded-full'
                                    : table.shape === 'SQUARE'
                                      ? 'size-14 rounded'
                                      : 'h-12 w-20 rounded',
                                selectedTableId === table.id
                                    ? 'ring-2 ring-mori-wine ring-offset-2'
                                    : '',
                                table.status === 'AVAILABLE'
                                    ? 'cursor-pointer'
                                    : 'cursor-not-allowed',
                            ]"
                            :style="tablePosition(table)"
                            :disabled="table.status !== 'AVAILABLE'"
                            :aria-pressed="selectedTableId === table.id"
                            :aria-label="`Table ${table.label}, seats ${table.capacity}, ${statusLabel(tableStatus(table))}`"
                            @click="selectedTableId = table.id"
                        >
                            {{ table.label }}
                        </button>
                    </template>
                </div>

                <p
                    v-if="availability && !availabilityLoading"
                    class="mt-3 text-xs text-mori-muted"
                >
                    {{ availability.tables.filter((t) => t.status === 'AVAILABLE').length }}
                    of {{ availability.tables.length }} tables available for
                    {{ form.party_size }} guests at {{ form.time }} on
                    {{ formatDate(availability.start_time) }}.
                </p>

                <p
                    v-if="availability?.tables.length === 0"
                    class="mt-4 text-sm text-mori-muted"
                >
                    This restaurant has no tables configured yet.
                </p>
            </section>
        </div>
    </div>
</template>
