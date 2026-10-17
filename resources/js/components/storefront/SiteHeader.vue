<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, ShoppingBag, User, X } from '@lucide/vue';
import BrandLogo from '@/components/mori/BrandLogo.vue';
import { useCart } from '@/composables/useCart';
import type { RestaurantContext } from '@/types/inertia';

const page = usePage();
const { itemCount } = useCart();
const open = ref(false);

const user = computed(() => page.props.auth?.user ?? null);
const restaurant = computed(
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

                <Link
                    :href="user ? '/orders' : '/login'"
                    class="hidden size-10 items-center justify-center rounded text-mori-ink hover:bg-mori-cream sm:inline-flex"
                    :aria-label="user ? 'My account' : 'Log in'"
                >
                    <User class="size-5" aria-hidden="true" />
                </Link>

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
                <Link
                    :href="user ? '/orders' : '/login'"
                    class="rounded px-2 py-3 text-sm text-mori-ink hover:bg-mori-cream"
                    @click="open = false"
                >
                    {{ user ? 'My account' : 'Log in' }}
                </Link>
            </nav>
        </div>

        <div
            v-if="restaurant"
            class="border-t border-mori-line bg-mori-cream/60 py-2 text-center text-[11px] tracking-[0.18em] text-mori-muted uppercase"
        >
            {{ restaurant.name }}
        </div>
    </header>
</template>
