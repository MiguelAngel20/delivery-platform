import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { enablePushForCurrentUser } from '@/lib/push/devices';
import { pushSupported, type PushWebConfig } from '@/lib/push/firebase';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    push?: {
        enabled?: boolean;
        vapid_key?: string;
        web?: PushWebConfig;
    };
};

type PromptTone = 'customer' | 'driver' | 'business' | 'admin';

const copy: Record<
    PromptTone,
    { title: string; body: string; storageKey: string }
> = {
    customer: {
        title: '¿Quieres recibir actualizaciones de tus pedidos?',
        body: 'Activa las notificaciones para enterarte cuando tu pedido avance.',
        storageKey: 'ride_push_prompt_customer',
    },
    driver: {
        title: 'Activa las notificaciones para recibir nuevos pedidos',
        body: 'Así te avisamos aunque la app esté en segundo plano.',
        storageKey: 'ride_push_prompt_driver',
    },
    business: {
        title: 'Activa avisos de nuevas comandas',
        body: 'Recibe notificaciones de pedidos, cancelaciones e incidencias.',
        storageKey: 'ride_push_prompt_business',
    },
    admin: {
        title: 'Activa alertas operativas',
        body: 'Pedidos RIDE, custom orders e incidencias importantes.',
        storageKey: 'ride_push_prompt_admin',
    },
};

type PushPermissionPromptProps = {
    tone: PromptTone;
    /** Show only when this is true (e.g. driver became available). */
    active?: boolean;
};

export function PushPermissionPrompt({
    tone,
    active = true,
}: PushPermissionPromptProps) {
    const { auth, push } = usePage().props as PageProps;
    const [visible, setVisible] = useState(false);
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        async function evaluate() {
            if (!active || !auth.user || !push?.enabled) {
                return;
            }

            if (!push.web || !push.vapid_key) {
                return;
            }

            if (!(await pushSupported())) {
                return;
            }

            if (Notification.permission !== 'default') {
                return;
            }

            try {
                if (localStorage.getItem(copy[tone].storageKey) === 'dismissed') {
                    return;
                }
            } catch {
                // ignore
            }

            if (!cancelled) {
                setVisible(true);
            }
        }

        void evaluate();

        return () => {
            cancelled = true;
        };
    }, [active, auth.user, push?.enabled, push?.vapid_key, push?.web, tone]);

    if (!visible) {
        return null;
    }

    const content = copy[tone];

    const dismiss = () => {
        try {
            localStorage.setItem(content.storageKey, 'dismissed');
        } catch {
            // ignore
        }

        setVisible(false);
    };

    const enable = async () => {
        if (!push?.web || !push.vapid_key) {
            return;
        }

        setBusy(true);
        const result = await enablePushForCurrentUser({
            web: push.web,
            vapidKey: push.vapid_key,
        });
        setBusy(false);

        if (result === 'granted') {
            dismiss();
            return;
        }

        if (result === 'denied') {
            dismiss();
            setMessage(
                'Permiso denegado. Puedes activarlo después en Configuración → Notificaciones.',
            );
            return;
        }

        if (result === 'unsupported') {
            setMessage(
                'Las notificaciones push no están disponibles en este navegador.',
            );
            dismiss();
            return;
        }

        setMessage('No se pudieron activar las notificaciones ahora.');
    };

    return (
        <div className="border-b border-border bg-primary/5 px-4 py-3">
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p className="text-sm font-semibold text-navy">
                        {content.title}
                    </p>
                    <p className="text-sm text-muted-foreground">{content.body}</p>
                    {message ? (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {message}
                        </p>
                    ) : null}
                </div>
                <div className="flex gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={dismiss}
                        disabled={busy}
                    >
                        Ahora no
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        onClick={() => void enable()}
                        disabled={busy}
                    >
                        Activar notificaciones
                    </Button>
                </div>
            </div>
        </div>
    );
}
