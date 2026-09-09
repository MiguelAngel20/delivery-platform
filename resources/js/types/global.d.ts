import type { Auth } from '@/types/auth';
import type { BusinessContext } from '@/types/business';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            businessContext: BusinessContext | null;
            sidebarOpen: boolean;
            loyalty?: import('@/apps/customer/components/loyalty-progress-card').LoyaltyProgress | null;
            [key: string]: unknown;
        };
    }
}
