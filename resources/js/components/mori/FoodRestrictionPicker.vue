<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type {
    FoodRestrictionCatalog,
    FoodRestrictionInput,
} from '@/types/restaurant';

/**
 * Shared allergy/restriction picker for registration and the account page.
 *
 * The model is the flat list the API accepts, so both forms validate and save
 * the same shape. Allergies, dietary restrictions and preferences are shown in
 * separate groups because they mean different things to a kitchen; "None" is
 * the empty list and never writes a placeholder record.
 */
const props = withDefaults(
    defineProps<{
        catalog: FoodRestrictionCatalog;
        modelValue: FoodRestrictionInput[];
        errors?: Record<string, string | undefined>;
        /** Prefix for input ids/names, e.g. `food_restrictions`. */
        idPrefix?: string;
        legend?: string;
        class?: string;
    }>(),
    {
        idPrefix: 'food-restrictions',
        legend: 'Do you have any food allergies or dietary restrictions we should know about?',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: FoodRestrictionInput[]];
}>();

const groups = computed(() => [
    {
        key: 'allergies',
        title: 'Allergies',
        hint: 'Reactions to a food that your body treats as harmful. These are compared with the allergens recorded for each dish.',
        options: props.catalog.allergies,
    },
    {
        key: 'dietary_restrictions',
        title: 'Dietary restrictions',
        hint: 'Medical needs or intolerances. The kitchen is told about these; they never change the menu filters.',
        options: props.catalog.dietary_restrictions,
    },
    {
        key: 'dietary_preferences',
        title: 'Dietary preferences',
        hint: 'Lifestyle choices, such as eating vegetarian or keto.',
        options: props.catalog.dietary_preferences,
    },
]);

const selected = computed(
    () => new Map(props.modelValue.map((entry) => [entry.restriction, entry])),
);

function isSelected(value: string): boolean {
    return selected.value.has(value);
}

function customLabelFor(value: string): string {
    return selected.value.get(value)?.custom_label ?? '';
}

/**
 * Add or remove one restriction. Custom labels are preserved while the "Other"
 * entry stays selected so typing does not lose focus or text.
 */
function toggle(value: string): void {
    const current = [...props.modelValue];
    const index = current.findIndex((entry) => entry.restriction === value);

    if (index >= 0) {
        current.splice(index, 1);
    } else {
        current.push({ restriction: value, custom_label: null });
    }

    emit('update:modelValue', current);
}

function setCustomLabel(value: string, label: string): void {
    const current = props.modelValue.map((entry) =>
        entry.restriction === value
            ? { ...entry, custom_label: label === '' ? null : label }
            : entry,
    );

    emit('update:modelValue', current);
}

function clearAll(): void {
    emit('update:modelValue', []);
}

/**
 * Errors are keyed by index on the server, which shifts as the list changes.
 * Showing the first one per group is enough to point the customer at the form
 * without pretending we can re-associate every message with its row.
 */
const groupError = computed(() => {
    const messages = Object.entries(props.errors ?? {})
        .filter(
            ([key]) =>
                key.startsWith(`${props.idPrefix}.`) && props.errors?.[key],
        )
        .map(([, message]) => message as string);

    return messages[0];
});
</script>

<template>
    <fieldset :class="cn('min-w-0', props.class)">
        <legend class="text-sm font-medium text-mori-ink">
            {{ legend }}
        </legend>
        <p class="mt-1 text-xs leading-relaxed text-mori-muted">
            Optional. You can change these at any time from your account. We
            store them so a dish page can point out ingredients that match —
            they are never treated as a guarantee of safety.
        </p>

        <div class="mt-4 space-y-5">
            <div v-for="group in groups" :key="group.key">
                <h3
                    class="text-[11px] font-semibold tracking-[0.18em] text-mori-muted uppercase"
                >
                    {{ group.title }}
                </h3>
                <p class="mt-1 text-xs text-mori-muted">{{ group.hint }}</p>

                <div class="mt-2 flex flex-wrap gap-2">
                    <label
                        v-for="option in group.options"
                        :key="`${group.key}-${option.value}`"
                        :class="
                            cn(
                                'inline-flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm transition-colors',
                                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-mori-wine',
                                isSelected(option.value)
                                    ? 'border-mori-wine bg-mori-wine/5 text-mori-wine'
                                    : 'border-mori-field bg-mori-paper text-mori-ink hover:border-mori-muted',
                            )
                        "
                    >
                        <!--
                            Intentionally unnamed: the owning form serialises
                            the flat list itself. A checkbox array here would
                            collide with those indexed fields.
                        -->
                        <input
                            type="checkbox"
                            class="size-4 accent-mori-wine"
                            :value="option.value"
                            :checked="isSelected(option.value)"
                            @change="toggle(option.value)"
                        />
                        {{ option.label }}
                    </label>
                </div>

                <div
                    v-if="
                        group.options.some(
                            (option) =>
                                option.requires_custom_label &&
                                isSelected(option.value),
                        )
                    "
                    class="mt-3 space-y-2"
                >
                    <template
                        v-for="option in group.options.filter(
                            (entry) => entry.requires_custom_label,
                        )"
                        :key="`${group.key}-${option.value}-label`"
                    >
                        <div v-if="isSelected(option.value)">
                            <label
                                :for="`${idPrefix}-${option.value}`"
                                class="block text-xs font-medium text-mori-ink"
                            >
                                Please describe
                                {{ option.label.toLowerCase() }}
                            </label>
                            <input
                                :id="`${idPrefix}-${option.value}`"
                                type="text"
                                maxlength="100"
                                :value="customLabelFor(option.value)"
                                :placeholder="
                                    option.value === 'other-allergy'
                                        ? 'e.g. mustard'
                                        : 'e.g. garlic'
                                "
                                class="mori-field mt-1 h-10 px-3"
                                @input="
                                    setCustomLabel(
                                        option.value,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <p v-if="groupError" class="mt-3 text-sm text-[#7a1f1f]" role="alert">
            {{ groupError }}
        </p>

        <p
            v-if="modelValue.length === 0"
            class="mt-4 rounded border border-mori-line bg-mori-cream/60 px-3 py-2 text-xs text-mori-muted"
        >
            <span class="font-medium text-mori-ink">None selected</span> — no
            restrictions or allergies saved.
        </p>

        <button
            v-else
            type="button"
            class="mt-4 text-xs font-medium text-mori-wine underline underline-offset-4 hover:text-mori-wine-hover focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
            @click="clearAll"
        >
            Clear selection (none)
        </button>
    </fieldset>
</template>
