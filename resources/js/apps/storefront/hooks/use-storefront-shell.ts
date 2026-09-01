import { router, usePage } from '@inertiajs/react';
import { home } from '@/routes';

export function useStorefrontShell() {
    const page = usePage();
    const onHomePage = page.component === 'public/home';

    return {
        onHomePage,
        showBottomNav: onHomePage,
    };
}

export function storefrontGoBack(): void {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back();

        return;
    }

    router.visit(home());
}
