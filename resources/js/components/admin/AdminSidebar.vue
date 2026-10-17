<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    LayoutDashboard,
    Settings,
    ShoppingBag,
    UtensilsCrossed,
    Users,
} from '@lucide/vue';
import BrandLogo from '@/components/mori/BrandLogo.vue';
import type { RestaurantContext } from '@/types/inertia';

const page = usePage();

const restaurant = computed(
    () => page.props.restaurantContext as RestaurantContext | null,
);

const items = [
    { title: 'Overview', href: '/admin', icon: LayoutDashboard, exact: true },
    { title: 'Orders', href: '/admin/orders', icon: ShoppingBag, exact: false },
    {
        title: 'Reservations',
        href: '/admin/reservations',
        icon: CalendarDays,
        exact: false,
    },
    { title: 'Menu', href: '/admin/menu', icon: UtensilsCrossed, exact: false },
    { title: 'Customers', href: '/admin/customers', icon: Users, exact: false },
    { title: 'Settings', href: '/admin/settings', icon: Settings, exact: false },
];

function isActive(href: string, exact: boolean): boolean {
    const path = page.url.split('?')[0];

    return exact ? path === href : path.startsWith(href);
}
</script>

<template>
    <aside class="admin-sidebar hidden shrink-0 flex-col md:flex">
        <div class="border-b border-white/10 px-5 py-5">
            <BrandLogo inverse compact />
            <p class="mt-2 text-[11px] tracking-[0.16em] text-white/50 uppercase">
                {{ restaurant?.name ?? 'Back office' }}
            </p>
        </div>

        <nav class="flex-1 space-y-1 p-3" aria-label="Back office">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="flex items-center gap-3 rounded px-3 py-2.5 text-sm transition-colors"
                :class="
                    isActive(item.href, item.exact)
                        ? 'bg-white/10 font-medium text-white'
                        : 'text-white/70 hover:bg-white/5 hover:text-white'
                "
                :aria-current="
                    isActive(item.href, item.exact) ? 'page' : undefined
                "
            >
                <component :is="item.icon" class="size-4" aria-hidden="true" />
                {{ item.title }}
            </Link>
        </nav>

        <div class="border-t border-white/10 p-3">
            <Link
                href="/"
                class="flex items-center gap-3 rounded px-3 py-2.5 text-sm text-white/60 hover:bg-white/5 hover:text-white"
            >
                View storefront
            </Link>
        </div>
    </aside>
</template>
