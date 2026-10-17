<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { getInitials } from '@/composables/useInitials';

defineProps<{
    title: string;
    subtitle?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const restaurant = computed(() => page.props.restaurantContext);

const navigation = [
    { title: 'Overview', href: '/admin' },
    { title: 'Orders', href: '/admin/orders' },
    { title: 'Reservations', href: '/admin/reservations' },
    { title: 'Menu', href: '/admin/menu' },
    { title: 'Customers', href: '/admin/customers' },
    { title: 'Settings', href: '/admin/settings' },
];
</script>

<template>
    <div class="border-b border-[#e3e5df] bg-white">
        <div
            class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 lg:px-8"
        >
            <div>
                <h1 class="mori-heading text-xl text-mori-ink">{{ title }}</h1>
                <p class="mt-0.5 text-xs text-mori-muted">
                    {{ subtitle ?? restaurant?.name }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium text-mori-ink">
                        {{ user?.name }}
                    </p>
                    <p class="text-xs text-mori-muted">{{ user?.email }}</p>
                </div>
                <span
                    class="flex size-9 items-center justify-center rounded-full bg-mori-sand/50 text-xs font-semibold text-mori-ink"
                    aria-hidden="true"
                >
                    {{ getInitials(user?.name) }}
                </span>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="inline-flex size-9 items-center justify-center rounded border border-[#e3e5df] text-mori-muted hover:bg-mori-cream"
                    aria-label="Log out"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>

        <nav
            class="flex gap-1 overflow-x-auto px-3 pb-2 md:hidden"
            aria-label="Back office"
        >
            <Link
                v-for="item in navigation"
                :key="item.href"
                :href="item.href"
                class="rounded px-3 py-2 text-xs whitespace-nowrap text-mori-muted hover:bg-mori-cream"
            >
                {{ item.title }}
            </Link>
        </nav>
    </div>
</template>
