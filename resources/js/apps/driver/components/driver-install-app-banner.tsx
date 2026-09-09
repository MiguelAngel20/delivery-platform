import { Share2, Smartphone, X } from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    detectInstallPlatform,
    dismissInstallBanner,
    getInstallBannerReminderRemainingMs,
    INSTALL_BANNER_INITIAL_DELAY_MS,
    INSTALL_BANNER_REMINDER_MS,
    isMobileUserAgent,
    isStandaloneApp,
    registerPwaServiceWorker,
    type InstallPlatform,
} from '@/lib/pwa/platform';
import { useInstallPrompt } from '@/lib/pwa/use-install-prompt';
import { cn } from '@/lib/utils';

const appName = 'ChisDrive Repartidor';
const BANNER_SCOPE = 'driver' as const;

type InstallCopy = {
    title: string;
    body: string;
    actionLabel: string;
};

function copyForPlatform(platform: InstallPlatform): InstallCopy {
    switch (platform) {
        case 'android':
            return {
                title: `Instala ${appName}`,
                body: 'Accede a tus pedidos desde la pantalla de inicio, como una app.',
                actionLabel: 'Instalar app',
            };
        case 'ios':
            return {
                title: `Agrega ${appName}`,
                body: 'Toca Compartir y luego “Agregar a pantalla de inicio”.',
                actionLabel: 'Entendido',
            };
        default:
            return {
                title: `Usa ${appName}`,
                body: 'Agrega este sitio a tu pantalla de inicio desde el menú del navegador.',
                actionLabel: 'Entendido',
            };
    }
}

function shouldShowBanner(
    platform: InstallPlatform,
    canInstall: boolean,
): boolean {
    if (platform === 'ios') {
        return true;
    }

    if (platform === 'android') {
        return canInstall;
    }

    return false;
}

export function DriverInstallAppBanner() {
    const { canInstall, install } = useInstallPrompt();
    const [visible, setVisible] = useState(false);
    const [platform, setPlatform] = useState<InstallPlatform>('unsupported');
    const reminderTimerRef = useRef<number | null>(null);

    const clearReminderTimer = useCallback(() => {
        if (reminderTimerRef.current !== null) {
            window.clearTimeout(reminderTimerRef.current);
            reminderTimerRef.current = null;
        }
    }, []);

    const tryShowBanner = useCallback(() => {
        if (!isMobileUserAgent() || isStandaloneApp()) {
            setVisible(false);

            return;
        }

        const detectedPlatform = detectInstallPlatform();
        setPlatform(detectedPlatform);

        if (!shouldShowBanner(detectedPlatform, canInstall)) {
            setVisible(false);

            return;
        }

        setVisible(true);
    }, [canInstall]);

    const scheduleReminder = useCallback(
        (delayMs: number = INSTALL_BANNER_REMINDER_MS) => {
            clearReminderTimer();

            reminderTimerRef.current = window.setTimeout(() => {
                reminderTimerRef.current = null;
                tryShowBanner();
            }, delayMs);
        },
        [clearReminderTimer, tryShowBanner],
    );

    useEffect(() => {
        void registerPwaServiceWorker();
    }, []);

    useEffect(() => {
        if (!isMobileUserAgent() || isStandaloneApp()) {
            setVisible(false);
            clearReminderTimer();

            return;
        }

        const remainingMs = getInstallBannerReminderRemainingMs(BANNER_SCOPE);

        if (remainingMs > 0) {
            setVisible(false);
            scheduleReminder(remainingMs);

            return () => clearReminderTimer();
        }

        const initialTimer = window.setTimeout(() => {
            tryShowBanner();
        }, INSTALL_BANNER_INITIAL_DELAY_MS);

        return () => {
            window.clearTimeout(initialTimer);
            clearReminderTimer();
        };
    }, [
        canInstall,
        clearReminderTimer,
        scheduleReminder,
        tryShowBanner,
    ]);

    const copy = useMemo(() => copyForPlatform(platform), [platform]);

    function hideAndScheduleReminder() {
        dismissInstallBanner(BANNER_SCOPE);
        setVisible(false);
        scheduleReminder();
    }

    async function handleInstall() {
        if (platform === 'ios' || platform === 'unsupported') {
            hideAndScheduleReminder();

            return;
        }

        const outcome = await install();

        if (outcome === 'accepted') {
            clearReminderTimer();
            setVisible(false);

            return;
        }

        if (outcome === 'dismissed') {
            hideAndScheduleReminder();
        }
    }

    function handleDismiss() {
        hideAndScheduleReminder();
    }

    if (!visible) {
        return null;
    }

    return (
        <div
            className={cn(
                'pointer-events-none fixed inset-x-0 bottom-20 z-50 flex justify-center px-4 md:hidden',
            )}
            role="region"
            aria-label="Instalar aplicación de repartidor"
        >
            <div className="pointer-events-auto w-full max-w-lg overflow-hidden rounded-2xl border border-border bg-surface shadow-lg">
                <div className="flex items-start gap-3 p-4">
                    <span className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                        {platform === 'ios' ? (
                            <Share2 className="size-5" />
                        ) : (
                            <Smartphone className="size-5" />
                        )}
                    </span>

                    <div className="min-w-0 flex-1 space-y-1">
                        <p className="font-semibold text-navy">{copy.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {copy.body}
                        </p>
                        {platform === 'ios' ? (
                            <p className="text-xs text-muted-foreground">
                                En Safari: botón{' '}
                                <span className="font-medium text-foreground">
                                    Compartir
                                </span>{' '}
                                →{' '}
                                <span className="font-medium text-foreground">
                                    Agregar a pantalla de inicio
                                </span>
                                .
                            </p>
                        ) : null}
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8 shrink-0 text-muted-foreground"
                        aria-label="Cerrar sugerencia de instalación"
                        onClick={handleDismiss}
                    >
                        <X className="size-4" />
                    </Button>
                </div>

                <div className="flex items-center justify-end gap-2 border-t border-border bg-muted/30 px-4 py-3">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={handleDismiss}
                    >
                        Ahora no
                    </Button>
                    <Button type="button" size="sm" onClick={handleInstall}>
                        {copy.actionLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
