<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head } from '@inertiajs/vue3';
import { Eye, EyeOff, UserPlus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import AppButton from '@/components/mori/AppButton.vue';
import FoodRestrictionPicker from '@/components/mori/FoodRestrictionPicker.vue';
import TextLink from '@/components/TextLink.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { store } from '@/routes/register';
import type {
    FoodRestrictionCatalog,
    FoodRestrictionInput,
} from '@/types/restaurant';

const props = defineProps<{
    passwordRules: string;
    foodRestrictionCatalog: FoodRestrictionCatalog;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description:
            'Save your allergies and dietary needs once, and see them beside every dish.',
    },
});

const showPassword = ref(false);
const restrictions = ref<FoodRestrictionInput[]>([]);
</script>

<template>
    <Head title="Create an account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name" class="text-mori-ink">Full name</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Your full name"
                    class="mori-field h-11"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email" class="text-mori-ink">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="you@example.com"
                    class="mori-field h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone" class="text-mori-ink">
                    Phone
                    <span class="font-normal text-mori-muted">(optional)</span>
                </Label>
                <Input
                    id="phone"
                    type="tel"
                    :tabindex="3"
                    autocomplete="tel"
                    name="phone"
                    placeholder="So we can reach you about an order"
                    class="mori-field h-11"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="password" class="text-mori-ink">Password</Label>
                <div class="relative">
                    <Input
                        id="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password"
                        :type="showPassword ? 'text' : 'password'"
                        placeholder="Choose a password"
                        :passwordrules="props.passwordRules"
                        class="mori-field h-11 pr-11"
                    />
                    <button
                        type="button"
                        class="absolute inset-y-0 right-0 flex items-center px-3 text-mori-muted hover:text-mori-ink focus-visible:rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                        :aria-label="
                            showPassword ? 'Hide password' : 'Show password'
                        "
                        :aria-pressed="showPassword"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff
                            v-if="showPassword"
                            class="size-4"
                            aria-hidden="true"
                        />
                        <Eye v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation" class="text-mori-ink">
                    Confirm password
                </Label>
                <Input
                    id="password_confirmation"
                    required
                    :tabindex="5"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                    placeholder="Repeat your password"
                    :passwordrules="props.passwordRules"
                    class="mori-field h-11"
                />
                <InputError :message="errors.password_confirmation" />
            </div>
        </div>

        <div class="border-t border-mori-line pt-6">
            <FoodRestrictionPicker
                v-model="restrictions"
                :catalog="props.foodRestrictionCatalog"
                :errors="errors"
                id-prefix="food_restrictions"
            />

            <!--
                The picker's state is mirrored into hidden inputs so the normal
                Inertia form submission carries it to Fortify without a second
                request.
            -->
            <input
                v-for="(entry, index) in restrictions"
                :key="`restriction-${entry.restriction}`"
                type="hidden"
                :name="`food_restrictions[${index}][restriction]`"
                :value="entry.restriction"
            />
            <input
                v-for="(entry, index) in restrictions"
                :key="`label-${entry.restriction}`"
                type="hidden"
                :name="`food_restrictions[${index}][custom_label]`"
                :value="entry.custom_label ?? ''"
            />
        </div>

        <AppButton
            type="submit"
            size="lg"
            block
            :tabindex="6"
            :loading="processing"
            :disabled="processing"
            data-test="register-user-button"
        >
            <UserPlus v-if="!processing" class="size-4" aria-hidden="true" />
            {{ processing ? 'Creating your account…' : 'Create account' }}
        </AppButton>

        <p class="text-center text-xs leading-relaxed text-mori-muted">
            New accounts are customer accounts. Staff and administrator access
            is granted by a restaurant administrator, never chosen here.
        </p>

        <div
            class="border-t border-mori-line pt-5 text-center text-sm text-mori-muted"
        >
            Already have an account?
            <TextLink
                :href="login()"
                class="font-medium text-mori-wine hover:text-mori-wine-hover"
                :tabindex="7"
            >
                Log in
            </TextLink>
        </div>
    </Form>
</template>
