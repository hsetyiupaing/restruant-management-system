import type { Auth } from '@/types/auth';
import type { FoodRestrictionEntry } from '@/types/restaurant';

export type RestaurantContext = {
    id: number;
    name: string;
    slug: string;
};

export type FlashToast = {
    type: 'success' | 'error' | 'info' | 'warning';
    message: string;
};

export type BreadcrumbItem = {
    title: string;
    href: string;
};

export type NavItem = {
    title: string;
    href: string;
    icon?: unknown;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth & { foodRestrictions?: FoodRestrictionEntry[] };
            sidebarOpen: boolean;
            restaurantContext: RestaurantContext | null;
            flash?: { toast?: FlashToast };
            [key: string]: unknown;
        };
    }
}
