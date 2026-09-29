import { router } from '@inertiajs/react';
import { releasePushTokenBeforeLogout } from '@/lib/auth/release-push-token';
import { deactivateStoredPushDevice } from '@/lib/push/devices';
import { logout } from '@/routes';

export async function logoutAfterPushCleanup(
    beforeNavigate?: () => void,
): Promise<void> {
    await releasePushTokenBeforeLogout(() => deactivateStoredPushDevice());
    beforeNavigate?.();
    router.flushAll();
    router.post(logout.url());
}
