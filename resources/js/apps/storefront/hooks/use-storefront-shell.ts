import { router, usePage } from '@inertiajs/react';
import { home } from '@/routes';
import customer from '@/routes/customer';
import restaurants from '@/routes/restaurants';

export function useStorefrontShell() {
    const page = usePage();
    const onHomePage = page.component === 'public/home';

    return {
        onHomePage,
        showBottomNav: onHomePage,
    };
}

type InertiaHistoryState = {
    page?: {
        component?: string;
    };
};

function currentPageComponent(): string | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const state = window.history.state as InertiaHistoryState | null;

    return state?.page?.component ?? null;
}

/**
 * Storefront back control: always use an Inertia visit (never history.back).
 * Raw history.back() triggers Chrome soft-navigation metrics that throw
 * "Cannot read properties of undefined (reading 'startTime')" / reportAllChanges
 * after flows like delivery-location dialog → restaurants.
 */
export function storefrontGoBack(): void {
    const component = currentPageComponent();

    const href = (() => {
        switch (component) {
            case 'public/restaurants/show':
                return restaurants.index.url();
            case 'customer/orders/show':
                return customer.orders.index.url();
            case 'customer/custom-orders/show':
            case 'customer/custom-orders/create':
                return customer.customOrders.index.url();
            case 'customer/notifications/preferences':
                return customer.profile.index.url();
            default:
                return home.url();
        }
    })();

    router.visit(href, {
        preserveState: false,
        preserveScroll: false,
    });
}
