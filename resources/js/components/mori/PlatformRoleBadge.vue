<script setup lang="ts">
import AppBadge from '@/components/mori/AppBadge.vue';
import { userRoleLabel } from '@/types/restaurant';
import type { UserRole } from '@/types/restaurant';

const props = defineProps<{
    role: UserRole;
}>();

const roleTones: Record<UserRole, 'neutral' | 'amber' | 'green' | 'wine'> = {
    CUSTOMER: 'neutral',
    STAFF: 'amber',
    ADMIN: 'green',
    SUPER_ADMIN: 'wine',
};
</script>

<template>
    <AppBadge :tone="roleTones[props.role]">
        {{ userRoleLabel(props.role) }}
    </AppBadge>
</template>
