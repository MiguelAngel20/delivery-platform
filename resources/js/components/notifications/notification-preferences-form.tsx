import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ContentCard, PageContainer, PageHeader } from '@/components/layout/page';
import { BackButton } from '@/components/navigation/back-button';
import { Button } from '@/components/ui/button';
import { preferenceLabel } from '@/lib/notifications/helpers';
import { enablePushForCurrentUser } from '@/lib/push/devices';
import { pushSupported, type PushWebConfig } from '@/lib/push/firebase';

type Preferences = Record<string, boolean>;

type Props = {
    preferences: Preferences;
    editable_keys: string[];
    role: string;
    update_url: string;
    back_href?: string;
};

type PageProps = {
    push?: {
        enabled?: boolean;
        vapid_key?: string;
        web?: PushWebConfig;
    };
};

export function NotificationPreferencesForm({
    preferences,
    editable_keys,
    update_url,
    back_href,
}: Props) {
    const { push } = usePage().props as PageProps;
    const [supported, setSupported] = useState(true);
    const [status, setStatus] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const initial: Preferences = {};
    for (const key of editable_keys) {
        initial[key] = Boolean(preferences[key]);
    }

    const form = useForm<Preferences>(initial);

    useEffect(() => {
        void pushSupported().then(setSupported);
    }, []);

    const activatePush = async () => {
        if (!push?.web || !push.vapid_key) {
            setStatus(
                'Push no está configurado en este entorno (PUSH_ENABLED / Firebase).',
            );
            return;
        }

        setBusy(true);
        const result = await enablePushForCurrentUser({
            web: push.web,
            vapidKey: push.vapid_key,
        });
        setBusy(false);

        setStatus(
            result === 'granted'
                ? 'Notificaciones activadas en este dispositivo.'
                : result === 'denied'
                  ? 'Permiso denegado por el navegador.'
                  : result === 'unsupported'
                    ? 'Las notificaciones push no están disponibles en este navegador.'
                    : 'No se pudo registrar el dispositivo.',
        );
    };

    return (
        <>
            <Head title="Notificaciones" />
            <PageContainer className="gap-4 px-4 py-4 md:px-6">
                <PageHeader
                    title="Notificaciones"
                    description="Preferencias transaccionales de RIDE"
                    actions={
                        back_href ? (
                            <BackButton href={back_href} />
                        ) : undefined
                    }
                />

                <ContentCard title="Este dispositivo">
                    {!supported ? (
                        <p className="text-sm text-muted-foreground">
                            Las notificaciones push no están disponibles en este
                            navegador.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-sm text-muted-foreground">
                                Activa avisos cuando la app esté en segundo
                                plano. Con la app abierta, Reverb actualiza la
                                interfaz.
                            </p>
                            <Button
                                type="button"
                                onClick={() => void activatePush()}
                                disabled={busy || !push?.enabled}
                            >
                                Activar notificaciones
                            </Button>
                        </div>
                    )}
                    {status ? (
                        <p className="mt-3 text-sm text-muted-foreground">
                            {status}
                        </p>
                    ) : null}
                </ContentCard>

                <ContentCard title="Preferencias">
                    <form
                        className="space-y-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(update_url, { preserveScroll: true });
                        }}
                    >
                        {editable_keys.map((key) => (
                            <label
                                key={key}
                                className="flex items-center justify-between gap-3 border-b border-border pb-3 last:border-0 last:pb-0"
                            >
                                <span className="text-sm text-foreground">
                                    {preferenceLabel(key)}
                                </span>
                                <input
                                    type="checkbox"
                                    checked={Boolean(form.data[key])}
                                    onChange={(event) =>
                                        form.setData(
                                            key,
                                            event.target.checked,
                                        )
                                    }
                                    className="size-4 accent-primary"
                                />
                            </label>
                        ))}
                        <Button type="submit" disabled={form.processing}>
                            Guardar
                        </Button>
                    </form>
                </ContentCard>
            </PageContainer>
        </>
    );
}

export default NotificationPreferencesForm;
