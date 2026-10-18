<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Minus, Plus, ShoppingBag, Trash2 } from '@lucide/vue';
import AppButton from '@/components/mori/AppButton.vue';
import EmptyState from '@/components/mori/EmptyState.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { ApiError, api, errorMessage } from '@/lib/api';
import { formatMoney } from '@/lib/format';
import { useCart } from '@/composables/useCart';
import type { Order, OrderType, Restaurant } from '@/types/restaurant';

const props = defineProps<{
    restaurant: Restaurant | null;
}>();

const {
    lines,
    subtotal,
    itemCount,
    setQuantity,
    remove,
    lineKey,
    clear,
    toOrderItems,
} = useCart();

const orderType = ref<OrderType>('PICKUP');
const submitting = ref(false);
const formError = ref<string | null>(null);
const fieldErrors = ref<Record<string, string[]>>({});
const customerNote = ref('');

/**
 * One key per checkout attempt so a double submit returns the same order.
 * It is replaced after a successful order.
 */
const idempotencyKey = ref(crypto.randomUUID());

const delivery = reactive({
    recipient_name: '',
    recipient_phone: '',
    address_line: '',
});

const settings = computed(() => props.restaurant?.settings ?? null);

const deliveryFee = computed(() => {
    if (orderType.value !== 'DELIVERY' || !settings.value) {
        return 0;
    }

    return Number.parseFloat(settings.value.delivery_fee) || 0;
});

const taxRate = computed(() =>
    settings.value ? Number.parseFloat(settings.value.tax_rate) || 0 : 0,
);

const tax = computed(
    () => Math.round(subtotal.value * taxRate.value * 100) / 100,
);

const total = computed(
    () =>
        Math.round((subtotal.value + deliveryFee.value + tax.value) * 100) /
        100,
);

const belowMinimum = computed(() => {
    if (orderType.value !== 'DELIVERY' || !settings.value) {
        return false;
    }

    const minimum = Number.parseFloat(settings.value.min_delivery_amount) || 0;

    return subtotal.value < minimum;
});

const canSubmit = computed(
    () =>
        itemCount.value > 0 &&
        props.restaurant !== null &&
        !belowMinimum.value &&
        !submitting.value,
);

function errorFor(field: string): string | null {
    return fieldErrors.value[field]?.[0] ?? null;
}

async function placeOrder(): Promise<void> {
    if (!canSubmit.value || !props.restaurant) {
        return;
    }

    submitting.value = true;
    formError.value = null;
    fieldErrors.value = {};

    const body = {
        restaurant_id: props.restaurant.id,
        order_type: orderType.value,
        items: toOrderItems(),
        customer_note: customerNote.value.trim() || null,
        ...(orderType.value === 'DELIVERY'
            ? {
                  delivery: {
                      recipient_name: delivery.recipient_name.trim(),
                      recipient_phone: delivery.recipient_phone.trim(),
                      address_line: delivery.address_line.trim(),
                  },
              }
            : {}),
    };

    try {
        const response = await api.post<{ data: Order }>('/orders', body, {
            headers: { 'Idempotency-Key': idempotencyKey.value },
        });

        clear();
        router.visit(`/orders/${response.data.id}`);
    } catch (caught) {
        if (caught instanceof ApiError) {
            fieldErrors.value = caught.errors;
        }

        formError.value = errorMessage(caught);
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <Head title="Checkout · MORI" />

    <div class="mori-container py-12">
        <h1 class="mori-heading text-3xl text-mori-ink sm:text-4xl">
            Checkout
        </h1>
        <p v-if="restaurant" class="mt-2 text-sm text-mori-muted">
            {{ restaurant.name }}
        </p>

        <EmptyState
            v-if="itemCount === 0"
            class="mt-8"
            :icon="ShoppingBag"
            title="Your order is empty"
            description="Add a few dishes from the menu and come back."
        >
            <AppButton href="/menu" size="sm">Browse the menu</AppButton>
        </EmptyState>

        <div v-else class="mt-8 grid gap-8 lg:grid-cols-[1fr_360px]">
            <div class="space-y-6">
                <section class="rounded border border-mori-line bg-mori-paper">
                    <h2
                        class="border-b border-mori-line px-5 py-3 text-sm font-semibold text-mori-ink"
                    >
                        Your items
                    </h2>
                    <ul class="divide-y divide-mori-line/70">
                        <li
                            v-for="line in lines"
                            :key="lineKey(line)"
                            class="flex items-start gap-4 px-5 py-4"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-mori-ink">
                                    {{ line.name }}
                                </p>
                                <p
                                    v-if="line.note"
                                    class="mt-0.5 text-xs text-mori-muted"
                                >
                                    {{ line.note }}
                                </p>
                                <ul
                                    v-if="line.selectedOptions?.length"
                                    class="mt-1 space-y-0.5 text-xs text-mori-muted"
                                >
                                    <li
                                        v-for="group in line.selectedOptions"
                                        :key="group.groupId"
                                    >
                                        {{ group.groupName }}:
                                        {{
                                            group.options
                                                .map((option) => option.name)
                                                .join(', ')
                                        }}
                                    </li>
                                </ul>
                                <p class="mt-0.5 text-xs text-mori-muted">
                                    {{ formatMoney(line.price) }} each
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-1"
                                role="group"
                                :aria-label="`Quantity for ${line.name}`"
                            >
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded border border-mori-line text-mori-ink hover:bg-mori-cream"
                                    :aria-label="`Remove one ${line.name}`"
                                    @click="
                                        setQuantity(
                                            lineKey(line),
                                            line.quantity - 1,
                                        )
                                    "
                                >
                                    <Minus class="size-3" aria-hidden="true" />
                                </button>
                                <span class="w-8 text-center text-sm">
                                    {{ line.quantity }}
                                </span>
                                <button
                                    type="button"
                                    class="inline-flex size-7 items-center justify-center rounded border border-mori-line text-mori-ink hover:bg-mori-cream disabled:opacity-40"
                                    :disabled="line.quantity >= 99"
                                    :aria-label="`Add one ${line.name}`"
                                    @click="
                                        setQuantity(
                                            lineKey(line),
                                            line.quantity + 1,
                                        )
                                    "
                                >
                                    <Plus class="size-3" aria-hidden="true" />
                                </button>
                            </div>

                            <span class="w-20 text-right text-sm text-mori-ink">
                                {{
                                    formatMoney(
                                        Number(line.price) * line.quantity,
                                    )
                                }}
                            </span>

                            <button
                                type="button"
                                class="inline-flex size-7 items-center justify-center rounded text-mori-muted hover:bg-mori-cream hover:text-mori-wine"
                                :aria-label="`Remove ${line.name} from order`"
                                @click="remove(lineKey(line))"
                            >
                                <Trash2 class="size-4" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded border border-mori-line bg-mori-paper p-5"
                >
                    <h2 class="text-sm font-semibold text-mori-ink">
                        How would you like it?
                    </h2>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label
                            v-for="option in [
                                {
                                    value: 'PICKUP',
                                    title: 'Pickup',
                                    text: 'Collect from the counter.',
                                },
                                {
                                    value: 'DELIVERY',
                                    title: 'Delivery',
                                    text: 'Brought to your door.',
                                },
                            ]"
                            :key="option.value"
                            class="flex cursor-pointer gap-3 rounded border p-4 transition-colors"
                            :class="
                                orderType === option.value
                                    ? 'border-mori-ink bg-mori-cream/60'
                                    : 'border-mori-line hover:border-mori-ink/40'
                            "
                        >
                            <input
                                v-model="orderType"
                                type="radio"
                                name="order_type"
                                :value="option.value"
                                class="mt-1"
                            />
                            <span>
                                <span
                                    class="block text-sm font-medium text-mori-ink"
                                >
                                    {{ option.title }}
                                </span>
                                <span class="block text-xs text-mori-muted">
                                    {{ option.text }}
                                </span>
                            </span>
                        </label>
                    </div>

                    <div
                        v-if="orderType === 'DELIVERY'"
                        class="mt-5 grid gap-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5">
                            <Label for="recipient_name">Recipient name</Label>
                            <Input
                                id="recipient_name"
                                v-model="delivery.recipient_name"
                                autocomplete="name"
                                :aria-invalid="
                                    Boolean(errorFor('delivery.recipient_name'))
                                "
                            />
                            <p
                                v-if="errorFor('delivery.recipient_name')"
                                class="text-xs text-[#7a1f1f]"
                            >
                                {{ errorFor('delivery.recipient_name') }}
                            </p>
                        </div>
                        <div class="space-y-1.5">
                            <Label for="recipient_phone">Phone</Label>
                            <Input
                                id="recipient_phone"
                                v-model="delivery.recipient_phone"
                                type="tel"
                                autocomplete="tel"
                                :aria-invalid="
                                    Boolean(
                                        errorFor('delivery.recipient_phone'),
                                    )
                                "
                            />
                            <p
                                v-if="errorFor('delivery.recipient_phone')"
                                class="text-xs text-[#7a1f1f]"
                            >
                                {{ errorFor('delivery.recipient_phone') }}
                            </p>
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label for="address_line">Delivery address</Label>
                            <Textarea
                                id="address_line"
                                v-model="delivery.address_line"
                                rows="2"
                                autocomplete="street-address"
                                :aria-invalid="
                                    Boolean(errorFor('delivery.address_line'))
                                "
                            />
                            <p
                                v-if="errorFor('delivery.address_line')"
                                class="text-xs text-[#7a1f1f]"
                            >
                                {{ errorFor('delivery.address_line') }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded border border-mori-line bg-mori-paper p-5"
                >
                    <Label for="customer_note">Note for the kitchen</Label>
                    <Textarea
                        id="customer_note"
                        v-model="customerNote"
                        class="mt-2"
                        rows="3"
                        maxlength="1000"
                        placeholder="Allergies, cutlery, delivery instructions…"
                    />
                </section>
            </div>

            <aside class="lg:sticky lg:top-28 lg:self-start">
                <div class="rounded border border-mori-line bg-mori-paper p-5">
                    <h2 class="mori-heading text-lg text-mori-ink">Summary</h2>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-mori-muted">Subtotal</dt>
                            <dd>{{ formatMoney(subtotal) }}</dd>
                        </div>
                        <div
                            v-if="orderType === 'DELIVERY'"
                            class="flex justify-between"
                        >
                            <dt class="text-mori-muted">Delivery fee</dt>
                            <dd>{{ formatMoney(deliveryFee) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-mori-muted">
                                Tax
                                <span v-if="taxRate" class="text-xs">
                                    ({{ Math.round(taxRate * 1000) / 10 }}%)
                                </span>
                            </dt>
                            <dd>{{ formatMoney(tax) }}</dd>
                        </div>
                        <div
                            class="flex justify-between border-t border-mori-line pt-3 text-base font-semibold"
                        >
                            <dt>Estimated total</dt>
                            <dd class="font-display">
                                {{ formatMoney(total) }}
                            </dd>
                        </div>
                    </dl>

                    <p class="mt-2 text-xs text-mori-muted">
                        Estimate only. The restaurant recomputes every amount
                        when the order is placed.
                    </p>

                    <p
                        v-if="belowMinimum"
                        class="mt-3 rounded border border-[#e8d3a3] bg-[#fbf0d9] px-3 py-2 text-xs text-[#6b4a12]"
                        role="status"
                    >
                        Delivery orders start at
                        {{ formatMoney(settings?.min_delivery_amount ?? '0') }}.
                        Add
                        {{
                            formatMoney(
                                (Number(settings?.min_delivery_amount) || 0) -
                                    subtotal,
                            )
                        }}
                        more, or choose pickup.
                    </p>

                    <p
                        v-if="formError"
                        class="mt-3 rounded border border-[#f0c4c4] bg-[#fae9e9] px-3 py-2 text-sm text-[#7a1f1f]"
                        role="alert"
                    >
                        {{ formError }}
                    </p>

                    <AppButton
                        class="mt-5"
                        block
                        size="lg"
                        :loading="submitting"
                        :disabled="!canSubmit"
                        @click="placeOrder"
                    >
                        Place order
                    </AppButton>
                </div>
            </aside>
        </div>
    </div>
</template>
