<script setup lang="ts">
import { computed, ref } from 'vue';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import AppBadge from '@/components/mori/AppBadge.vue';
import AppModal from '@/components/mori/AppModal.vue';
import { useCart } from '@/composables/useCart';
import { formatMoney } from '@/lib/format';
import type { MenuItem } from '@/types/restaurant';

const props = defineProps<{
    dish: MenuItem;
    showAdd?: boolean;
}>();

const { add } = useCart();
const customizing = ref(false);
const note = ref('');
const added = ref(false);

const isSoldOut = computed(() => !props.dish.is_available);

function confirmAdd(): void {
    add(props.dish, 1, note.value.trim());
    note.value = '';
    customizing.value = false;
    added.value = true;
    window.setTimeout(() => {
        added.value = false;
    }, 1600);
}
</script>

<template>
    <article
        class="group flex flex-col overflow-hidden rounded border border-mori-line bg-mori-paper"
    >
        <div class="relative aspect-[4/3] overflow-hidden bg-mori-cream">
            <img
                v-if="dish.image_url"
                :src="dish.image_url"
                :alt="dish.name"
                loading="lazy"
                class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
            />
            <div
                v-else
                class="flex h-full w-full items-center justify-center"
                aria-hidden="true"
            >
                <span class="font-display text-4xl text-mori-sand">
                    {{ dish.name.charAt(0) }}
                </span>
            </div>
            <AppBadge
                v-if="dish.is_featured"
                tone="wine"
                class="absolute top-3 left-3 bg-mori-paper/95"
            >
                Signature
            </AppBadge>
            <AppBadge
                v-if="isSoldOut"
                tone="neutral"
                class="absolute top-3 right-3 bg-mori-ink/85 text-white"
            >
                Sold out
            </AppBadge>
        </div>

        <div class="flex flex-1 flex-col gap-2 p-4">
            <div class="flex items-start justify-between gap-3">
                <h3 class="mori-heading text-lg leading-snug text-mori-ink">
                    {{ dish.name }}
                </h3>
                <span class="font-display text-base whitespace-nowrap text-mori-wine">
                    {{ formatMoney(dish.price) }}
                </span>
            </div>
            <p
                v-if="dish.category"
                class="text-[11px] tracking-[0.16em] text-mori-muted uppercase"
            >
                {{ dish.category.name }}
            </p>
            <p class="line-clamp-2 text-sm text-mori-muted">
                {{ dish.description }}
            </p>

            <div v-if="showAdd !== false" class="mt-auto pt-3">
                <Button
                    type="button"
                    size="sm"
                    :disabled="isSoldOut"
                    class="w-full rounded bg-mori-ink text-mori-paper hover:bg-mori-ink/90"
                    @click="customizing = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    {{ added ? 'Added to order' : isSoldOut ? 'Unavailable' : 'Add to order' }}
                </Button>
            </div>
        </div>

        <AppModal
            v-model:open="customizing"
            :title="dish.name"
            :description="formatMoney(dish.price)"
        >
            <label for="dish-note" class="text-sm font-medium text-mori-ink">
                Special instructions
            </label>
            <Textarea
                id="dish-note"
                v-model="note"
                maxlength="255"
                placeholder="No onion, extra sauce, allergies…"
            />
            <template #footer>
                <Button variant="outline" @click="customizing = false">
                    Cancel
                </Button>
                <Button
                    class="bg-mori-wine text-white hover:bg-mori-wine-hover"
                    @click="confirmAdd"
                >
                    Add to order
                </Button>
            </template>
        </AppModal>
    </article>
</template>
