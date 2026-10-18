<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <!--
        `mori-field` (resources/css/mori.css) supplies the surface, the 3:1
        border and the focus ring, replacing shadcn's `bg-transparent` and
        `dark:bg-input/30`.
    -->
    <textarea
        v-model="modelValue"
        data-slot="textarea"
        :class="
            cn(
                'mori-field flex min-h-20 px-3 py-2 disabled:cursor-not-allowed',
                props.class,
            )
        "
    />
</template>
