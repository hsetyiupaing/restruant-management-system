<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, ShoppingBag, X } from '@lucide/vue';
import BrandLogo from '@/components/mori/BrandLogo.vue';
import AccountMenu from '@/components/storefront/AccountMenu.vue';
import { useCart } from '@/composables/useCart';
import type { RestaurantContext } from '@/types/inertia';

const page = usePage();
const { itemCount } = useCart();
const open = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
/**
 * Only staff/administrators resolve to a restaurant context, so this doubles
 * as the guard for the "Back office" link.
 */
const backOffice = computed(
    () => page.props.restaurantContext as RestaurantContext | null,
);

const links = computed(() => [
    { title: 'Home', href: '/', exact: true },
    { title: 'Menu', href: '/menu', exact: false },
    { title: 'Reserve', href: '/reserve', exact: false },
    ...(user.value
        ? [{ title: 'My orders', href: '/orders', exact: false }]
        : []),
]);

const primaryLinks = [
    { title: 'Home', href: '/', exact: true },
    { title: 'Menu', href: '/menu', exact: false },
    { title: 'Reserve', href: '/reserve', exact: false },
];

const cartHref = computed(() => {
    if (itemCount.value === 0) {
        return '/menu';
    }

    return user.value ? '/checkout' : '/login';
});

const cartLabel = computed(() => {
    if (itemCount.value === 0) {
        return 'Cart is empty. Browse menu';
    }

    if (!user.value) {
        return `Cart, ${itemCount.value} items. Sign in to checkout`;
    }

    return `Cart, ${itemCount.value} items. Go to checkout`;
});

function handleMobileLogout(): void {
    router.flushAll();
    open.value = false;
}

function isActive(href: string, exact: boolean): boolean {
    const path = page.url.split('?')[0];

    return exact ? path === href : path.startsWith(href);
}
</script>

<template>
    <header
        class="fixed inset-x-0 top-0 z-40 border-b border-mori-line bg-mori-paper/95 backdrop-blur"
    >
        <div
            class="mori-container flex h-16 items-center justify-between gap-2 sm:gap-4 md:h-[76px]"
        >
            <Link href="/" class="min-w-0 shrink" aria-label="MORI home">
                <BrandLogo />
            </Link>

            <nav class="hidden items-center gap-7 md:flex" aria-label="Main">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="text-sm transition-colors hover:text-mori-wine"
                    :class="
                        isActive(link.href, link.exact)
                            ? 'font-medium text-mori-wine'
                            : 'text-mori-muted'
                    "
                    :aria-current="
                        isActive(link.href, link.exact) ? 'page' : undefined
                    "
                >
                    {{ link.title }}
                </Link>
            </nav>

            <div class="flex items-center gap-1">
                <Link
                    v-if="backOffice"
                    href="/admin"
                    class="hidden rounded px-3 py-2 text-sm text-mori-muted transition-colors hover:bg-mori-cream hover:text-mori-ink md:inline-flex"
                >
                    Back office
                </Link>

                <Link
                    :href="cartHref"
                    class="relative inline-flex size-10 items-center justify-center rounded text-mori-ink hover:bg-mori-cream focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-mori-wine"
                    :aria-label="cartLabel"
                >
                    <ShoppingBag class="size-5" aria-hidden="true" />
                    <span
                        v-if="itemCount > 0"
                        class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-mori-wine text-[10px] font-semibold text-white shadow-sm"
                    >
                        {{ itemCount }}
                    </span>
                </Link>

                <div class="hidden sm:block">
                    <AccountMenu />
                </div>

                <button
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded text-mori-ink hover:bg-mori-cream md:hidden"
                    :aria-expanded="open"
                    aria-controls="mobile-nav"
                    aria-label="Toggle navigation"
                    @click="open = !open"
                >
                    <X v-if="open" class="size-5" aria-hidden="true" />
                    <Menu v-else class="size-5" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div
            v-if="open"
            id="mobile-nav"
            class="border-t border-mori-line bg-mori-paper md:hidden"
        >
            <nav class="mori-container flex flex-col py-3" aria-label="Mobile">
                <Link
                    v-for="link in primaryLinks"
                    :key="link.href"
                    :href="link.href"
                    class="rounded px-3 py-2.5 text-sm font-medium text-mori-ink hover:bg-mori-cream"
                    @click="open = false"
                >
                    {{ link.title }}
                </Link>

                <div v-if="user" class="mt-2 border-t border-mori-line/70 pt-2">
                    <div class="px-3 py-2">
                        <p
                            class="text-xs font-semibold tracking-wider text-mori-muted uppercase"
                        >
                            Signed in as
                        </p>
                        <p class="truncate text-sm font-medium text-mori-ink">
                            {{ user.name }}
                        </p>
                    </div>
                    <Link
                        href="/orders"
                        class="block rounded px-3 py-2.5 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        My orders
                    </Link>
                    <Link
                        href="/account/dietary-restrictions"
                        class="block rounded px-3 py-2.5 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        My dietary restrictions
                    </Link>
                    <Link
                        href="/settings/profile"
                        class="block rounded px-3 py-2.5 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        Profile settings
                    </Link>
                    <Link
                        v-if="backOffice"
                        href="/admin"
                        class="block rounded px-3 py-2.5 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        Back office
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="block w-full rounded px-3 py-2.5 text-left text-sm font-medium text-mori-wine hover:bg-mori-cream"
                        data-test="storefront-logout-mobile"
                        @click="handleMobileLogout"
                    >
                        Sign out
                    </Link>
                </div>
                <div
                    v-else
                    class="mt-2 space-y-1 border-t border-mori-line/70 pt-2"
                >
                    <Link
                        href="/login"
                        class="block rounded px-3 py-2.5 text-sm font-medium text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        Log in
                    </Link>
                    <Link
                        href="/register"
                        class="block rounded px-3 py-2.5 text-sm font-medium text-mori-wine hover:bg-mori-cream"
                        @click="open = false"
                    >
                        Create an account
                    </Link>
                    <Link
                        v-if="backOffice"
                        href="/admin"
                        class="block rounded px-3 py-2.5 text-sm text-mori-muted hover:bg-mori-cream"
                        @click="open = false"
                    >
                        Back office
                    </Link>
                </div>
            </nav>
        </div>

        <div
            v-if="backOffice"
            class="border-t border-mori-line bg-mori-cream/60 py-2 text-center text-[11px] tracking-[0.18em] text-mori-muted uppercase"
        >
            {{ backOffice.name }}
        </div>
    </header>
</template>
