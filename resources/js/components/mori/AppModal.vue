<script setup lang="ts">
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';

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
    <!--
        The dialog is portalled to <body>, outside `.mori-shell` / `.admin-shell`
        where the palette tokens live. `.mori-dialog` re-declares them and pins
        `color-scheme: light`; without it `bg-background` went near-black in dark
        mode while the title stayed ink black — the "editing title cannot be
        seen" bug.
    -->
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-lg'"
            class="mori-dialog flex max-h-[85dvh] flex-col overflow-hidden font-body"
        >
            <DialogHeader class="shrink-0 border-b border-mori-line pr-8 pb-4">
                <DialogTitle class="mori-heading text-xl text-mori-ink">
                    {{ title }}
                </DialogTitle>
                <DialogDescription
                    v-if="description"
                    class="text-sm text-mori-muted"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div class="min-h-0 flex-1 overflow-y-auto py-1 pr-2">
                <slot />
            </div>

            <div
                v-if="$slots.footer"
                class="flex shrink-0 justify-end gap-2 border-t border-mori-line pt-4"
            >
                <slot name="footer" />
            </div>
        </DialogContent>
    </Dialog>
</template>
