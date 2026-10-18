<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
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

const links = [
    { title: 'Home', href: '/', exact: true },
    { title: 'Menu', href: '/menu', exact: false },
    { title: 'Reserve', href: '/reserve', exact: false },
    { title: 'My orders', href: '/orders', exact: false },
];

function isActive(href: string, exact: boolean): boolean {
    const path = page.url.split('?')[0];

    return exact ? path === href : path.startsWith(href);
}
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-mori-line bg-mori-paper/95 backdrop-blur"
    >
        <div
            class="mori-container flex h-16 items-center justify-between gap-4 md:h-[76px]"
        >
            <Link href="/" class="shrink-0" aria-label="MORI home">
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
                    href="/checkout"
                    class="relative inline-flex size-10 items-center justify-center rounded text-mori-ink hover:bg-mori-cream"
                    :aria-label="`Cart, ${itemCount} items`"
                >
                    <ShoppingBag class="size-5" aria-hidden="true" />
                    <span
                        v-if="itemCount > 0"
                        class="absolute -top-0.5 -right-0.5 flex size-5 items-center justify-center rounded-full bg-mori-wine text-[10px] font-semibold text-white"
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
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                    @click="open = false"
                >
                    {{ link.title }}
                </Link>
                <template v-if="user">
                    <Link
                        href="/account/dietary-restrictions"
                        class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        My dietary restrictions
                    </Link>
                    <Link
                        href="/orders"
                        class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                        @click="open = false"
                    >
                        My orders
                    </Link>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="rounded px-2 py-3 text-left text-sm text-mori-wine hover:bg-mori-cream"
                        data-test="storefront-logout-mobile"
                        @click="open = false"
                    >
                        Sign out
                    </Link>
                </template>
                <Link
                    v-else
                    href="/login"
                    class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                    @click="open = false"
                >
                    Log in
                </Link>
                <!--
                    Staff and administrators need a way back to the back office
                    from the storefront; customers never see it.
                -->
                <Link
                    v-if="backOffice"
                    href="/admin"
                    class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                    @click="open = false"
                >
                    Back office
                </Link>
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
