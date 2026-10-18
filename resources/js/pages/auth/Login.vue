<script setup lang="ts">
import { computed, ref } from 'vue';
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Eye, EyeOff } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import AppButton from '@/components/mori/AppButton.vue';
import TextLink from '@/components/TextLink.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import type { AuthUser } from '@/types/restaurant';

defineOptions({
    layout: {
        title: 'Welcome back',
        description:
            'Log in to see your saved dietary restrictions and orders.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const page = usePage();
const showPassword = ref(false);

/**
 * Fortify redirects by role on the server. This hint is shown below the button
 * so a staff or admin account knows where it is heading.
 */
const destinationHint = computed(() => {
    const user = page.props.auth?.user as AuthUser | null | undefined;

    if (!user) {
        return 'Sign in with the email address you registered with.';
    }

    return 'You are already signed in.';
});
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-5 text-center text-sm font-medium text-mori-sage"
        role="status"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email" class="text-mori-ink">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="you@example.com"
                    class="mori-field h-11"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password" class="text-mori-ink">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm text-mori-muted hover:text-mori-wine"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>

                <div class="relative">
                    <Input
                        id="password"
                        name="password"
                        required
                        :type="showPassword ? 'text' : 'password'"
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Your password"
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

            <Label
                for="remember"
                class="flex w-fit cursor-pointer items-center gap-2.5 text-sm text-mori-ink"
            >
                <Checkbox
                    id="remember"
                    name="remember"
                    :tabindex="3"
                    class="border-mori-ink/40 data-[state=checked]:border-mori-wine data-[state=checked]:bg-mori-wine"
                />
                <span>Keep me signed in</span>
            </Label>

            <AppButton
                type="submit"
                size="lg"
                block
                :tabindex="4"
                :loading="processing"
                :disabled="processing"
                data-test="login-button"
            >
                {{ processing ? 'Signing in…' : 'Log in' }}
            </AppButton>

            <p class="text-center text-xs text-mori-muted" aria-live="polite">
                {{ destinationHint }}
            </p>
        </div>

        <div
            class="border-t border-mori-line pt-5 text-center text-sm text-mori-muted"
        >
            Don't have an account?
            <TextLink
                :href="register()"
                class="font-medium text-mori-wine hover:text-mori-wine-hover"
                :tabindex="5"
            >
                Create one
            </TextLink>
        </div>
    </Form>
</template>
