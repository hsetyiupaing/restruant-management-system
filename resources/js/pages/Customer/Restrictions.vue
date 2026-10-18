<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Check, TriangleAlert } from '@lucide/vue';
import { toast } from 'vue-sonner';
import AppButton from '@/components/mori/AppButton.vue';
import FoodRestrictionPicker from '@/components/mori/FoodRestrictionPicker.vue';
import SectionHeading from '@/components/mori/SectionHeading.vue';
import { ApiError, api, errorMessage } from '@/lib/api';
import type {
    FoodRestrictionCatalog,
    FoodRestrictionEntry,
    FoodRestrictionInput,
} from '@/types/restaurant';

const props = defineProps<{
    catalog: FoodRestrictionCatalog;
    saved: FoodRestrictionEntry[];
}>();

const selections = ref<FoodRestrictionInput[]>([]);
const saving = ref(false);
const saved = ref(false);
const errors = ref<Record<string, string>>({});
const formError = ref<string | null>(null);

const savedLabels = computed(() =>
    props.saved.map((entry) => entry.label).join(', '),
);

/**
 * Seed the form from the server's copy on every visit, so returning to this
 * page always reflects what is actually stored rather than stale local state.
 */
function seed(): void {
    selections.value = props.saved.map((entry) => ({
        restriction: entry.restriction,
        custom_label: entry.custom_label,
    }));
}

async function save(): Promise<void> {
    saving.value = true;
    saved.value = false;
    errors.value = {};
    formError.value = null;

    try {
        await api.put('/auth/me/food-restrictions', {
            food_restrictions: selections.value,
        });

        // Reload shared props so the dish page picks the new list up on the
        // next visit without relying on browser state.
        router.reload({ only: ['auth'] });
        saved.value = true;
        toast.success('Your dietary restrictions were saved.');
        window.setTimeout(() => {
            saved.value = false;
        }, 2500);
    } catch (caught) {
        if (caught instanceof ApiError) {
            errors.value = Object.fromEntries(
                Object.entries(caught.errors).map(([key, messages]) => [
                    key,
                    messages[0] ?? '',
                ]),
            );
        }

        formError.value = errorMessage(caught);
    } finally {
        saving.value = false;
    }
}

onMounted(seed);
</script>

<template>
    <Head title="Dietary restrictions · MORI" />

    <div class="mori-container max-w-3xl py-12">
        <SectionHeading
            eyebrow="Your account"
            title="Dietary restrictions"
            text="Saved once and shown beside every dish, so you can spot ingredients that matter to you."
        >
            <template #action>
                <AppButton href="/menu" variant="outline" size="sm">
                    Browse the menu
                </AppButton>
            </template>
        </SectionHeading>

        <div
            class="mt-8 rounded border border-mori-line bg-mori-paper p-5 sm:p-6"
        >
            <FoodRestrictionPicker
                v-model="selections"
                :catalog="props.catalog"
                :errors="errors"
            />

            <div
                v-if="formError"
                class="mt-4 rounded border border-[#f0c4c4] bg-[#fae9e9] px-4 py-3 text-sm text-[#7a1f1f]"
                role="alert"
            >
                {{ formError }}
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <AppButton :loading="saving" :disabled="saving" @click="save">
                    <Check
                        v-if="saved && !saving"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{
                        saving
                            ? 'Saving…'
                            : saved
                              ? 'Saved'
                              : 'Save restrictions'
                    }}
                </AppButton>
                <AppButton variant="ghost" :disabled="saving" @click="seed">
                    Revert changes
                </AppButton>
            </div>

            <p
                class="mt-3 text-xs text-mori-muted"
                role="status"
                aria-live="polite"
            >
                Currently saved:
                <span class="font-medium text-mori-ink">
                    {{ savedLabels || 'none' }}
                </span>
            </p>
        </div>

        <p
            class="mt-6 flex gap-2.5 rounded border border-mori-line bg-mori-cream/70 px-4 py-3 text-xs leading-relaxed text-mori-muted"
            role="note"
        >
            <TriangleAlert
                class="mt-0.5 size-4 shrink-0 text-mori-wine"
                aria-hidden="true"
            />
            <span>
                These details help you compare dishes with your saved needs, but
                they are not a safety guarantee. Our kitchen shares equipment
                and preparation surfaces, and some dishes have incomplete
                ingredient records. If you have a serious allergy, confirm with
                restaurant staff before ordering.
            </span>
        </p>
    </div>
</template>
