<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LogOut, Receipt, ShieldCheck, Utensils } from '@lucide/vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { getInitials } from '@/composables/useInitials';
import type { AuthUser } from '@/types/restaurant';

/**
 * The customer's own account menu in the storefront header. It only ever shows
 * the signed-in user's own pages plus sign out; anything else would need an id
 * in the URL, and the account is resolved from the session instead.
 */
const page = usePage();
const user = computed<AuthUser | null>(
    () => (page.props.auth?.user as AuthUser | undefined) ?? null,
);

const items = [
    {
        title: 'My dietary restrictions',
        href: '/account/dietary-restrictions',
        icon: Utensils,
    },
    { title: 'My orders', href: '/orders', icon: Receipt },
    { title: 'Profile settings', href: '/settings/profile', icon: ShieldCheck },
];

function handleLogout(): void {
    // Drop any per-page state (cart, cached props) so the next visitor of this
    // browser cannot see the previous customer's data.
    router.flushAll();
}
</script>

<template>
    <template v-if="user">
        <DropdownMenu>
            <DropdownMenuTrigger
                class="inline-flex size-10 items-center justify-center rounded text-mori-ink hover:bg-mori-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                :aria-label="`Account menu for ${user.name}`"
            >
                <span
                    class="flex size-8 items-center justify-center rounded-full bg-mori-sand/50 text-[11px] font-semibold text-mori-ink"
                    aria-hidden="true"
                >
                    {{ getInitials(user.name) }}
                </span>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" class="w-60 font-body">
                <DropdownMenuLabel class="font-normal">
                    <p class="text-sm font-medium text-mori-ink">
                        {{ user.name }}
                    </p>
                    <p class="truncate text-xs text-mori-muted">
                        {{ user.email }}
                    </p>
                </DropdownMenuLabel>

                <DropdownMenuSeparator />

                <DropdownMenuItem
                    v-for="item in items"
                    :key="item.href"
                    :as-child="true"
                >
                    <Link
                        :href="item.href"
                        class="flex w-full cursor-pointer items-center"
                    >
                        <component
                            :is="item.icon"
                            class="mr-2 size-4"
                            aria-hidden="true"
                        />
                        {{ item.title }}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSeparator />

                <DropdownMenuItem :as-child="true">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="flex w-full cursor-pointer items-center text-mori-wine"
                        data-test="storefront-logout"
                        @click="handleLogout"
                    >
                        <LogOut class="mr-2 size-4" aria-hidden="true" />
                        Sign out
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </template>

    <Link
        v-else
        href="/login"
        class="inline-flex h-10 items-center justify-center rounded px-3 text-sm text-mori-muted transition-colors hover:bg-mori-cream hover:text-mori-ink"
    >
        Log in
    </Link>
</template>
