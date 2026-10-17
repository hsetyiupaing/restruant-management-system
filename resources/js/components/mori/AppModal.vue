<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        wide?: boolean;
    }>(),
    { wide: false },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-lg'"
            class="font-body"
        >
            <DialogHeader>
                <DialogTitle class="mori-heading text-xl">
                    {{ title }}
                </DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <slot />

            <div v-if="$slots.footer" class="flex justify-end gap-2">
                <slot name="footer" />
            </div>
        </DialogContent>
    </Dialog>
</template>
