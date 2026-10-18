<script setup lang="ts">
import { computed } from 'vue';
import AppBadge from '@/components/mori/AppBadge.vue';
import { statusLabel } from '@/lib/format';

const props = defineProps<{
    status: string;
}>();

type Tone =
    | 'neutral'
    | 'wine'
    | 'sage'
    | 'sand'
    | 'amber'
    | 'blue'
    | 'green'
    | 'red';

const tones: Record<string, Tone> = {
    PLACED: 'blue',
    CONFIRMED: 'blue',
    PREPARING: 'amber',
    READY: 'green',
    OUT_FOR_DELIVERY: 'sage',
    COMPLETED: 'green',
    CANCELLED: 'red',
    REJECTED: 'red',
    DELIVERY_FAILED: 'red',
    REFUNDED: 'neutral',
    PENDING: 'amber',
    SEATED: 'sage',
    NO_SHOW: 'red',
    EXPIRED: 'neutral',
    UNPAID: 'sand',
    PAID: 'green',
    FAILED: 'red',
    UNASSIGNED: 'neutral',
    ASSIGNED: 'blue',
    PICKED_UP: 'sage',
    DELIVERED: 'green',
};

const tone = computed<Tone>(() => tones[props.status] ?? 'neutral');
</script>

<template>
    <AppBadge :tone="tone">{{ statusLabel(status) }}</AppBadge>
</template>
