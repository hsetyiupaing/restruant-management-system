import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { syncCartOwner } from '@/composables/useCart';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import StorefrontLayout from '@/layouts/StorefrontLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

/**
 * The cart's browser namespace is chosen by the server (see
 * HandleInertiaRequests::cartOwner), so every visit—including login and
 * logout—must re-sync it. This is what stops one account's cart from being
 * shown to the next visitor of the same browser.
 */
function syncCartFromPage(
    pageProps: Record<string, unknown> | undefined,
): void {
    const cart = pageProps?.cart as { owner?: string | null } | undefined;

    syncCartOwner(cart?.owner ?? null);
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('Storefront/'):
            case name.startsWith('Customer/'):
            case name === 'Dashboard':
            case name === 'NotFound':
                return StorefrontLayout;
            case name.startsWith('Admin/'):
                return AdminLayout;
            default:
                return AppLayout;
        }
    },
    withApp: (app, { page }) => {
        app.directive('focus', {
            mounted: (el: HTMLElement, shouldFocus) => {
                if (shouldFocus.value !== false) {
                    el.focus();
                }
            },
        });

        // Initial page load: adopt the namespace the server assigned.
        syncCartFromPage(page.props);

        // Every subsequent Inertia visit carries fresh shared props. Re-sync
        // after login/logout so the badge reflects only the active cart.
        router.on('navigate', (event) => {
            syncCartFromPage(event.detail.page?.props);
        });
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
