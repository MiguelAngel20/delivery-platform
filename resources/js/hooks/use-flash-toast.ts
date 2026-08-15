import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import type { FlashToast } from '@/types/ui';

function showToast(data: FlashToast | undefined): void {
    if (!data?.message || !data.type) {
        return;
    }

    const fn = toast[data.type];

    if (typeof fn === 'function') {
        fn(data.message);
    }
}

export function useFlashToast(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const detail = (event as CustomEvent<{ flash?: { toast?: FlashToast } }>)
                .detail;
            showToast(detail?.flash?.toast);
        });
    }, []);

    useEffect(() => {
        return router.on('success', (event) => {
            const page = (event as CustomEvent<{ page?: { flash?: { toast?: FlashToast } } }>)
                .detail?.page;
            showToast(page?.flash?.toast);
        });
    }, []);
}
