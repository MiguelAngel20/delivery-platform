import { router } from '@inertiajs/react';

export type InboxNotification = {
    id: string;
    title: string;
    body: string;
    category?: string | null;
    type?: string | null;
    target_type?: string | null;
    target_id?: number | string | null;
    click_path?: string | null;
    read_at?: string | null;
    created_at?: string | null;
};

const LABEL_KEYS: Record<string, string> = {
    push_enabled: 'Notificaciones push',
    order_updates: 'Actualizaciones de pedidos',
    new_orders: 'Nuevas comandas',
    driver_offers: 'Nuevos pedidos disponibles',
    finance_updates: 'Finanzas',
    incident_updates: 'Incidencias',
    custom_order_updates: 'Pedidos personalizados',
    system_updates: 'Sistema',
};

export function preferenceLabel(key: string): string {
    return LABEL_KEYS[key] ?? key;
}

function safeInternalPath(path: string | null | undefined): string | null {
    if (
        typeof path !== 'string' ||
        !path.startsWith('/') ||
        path.startsWith('//') ||
        path.includes('://')
    ) {
        return null;
    }

    return path;
}

export function resolveNotificationPath(
    notification: Pick<
        InboxNotification,
        'target_type' | 'target_id' | 'type' | 'click_path'
    >,
    role: string | null | undefined,
): string | null {
    const fromPayload = safeInternalPath(notification.click_path);

    if (fromPayload) {
        return fromPayload;
    }

    const targetType = notification.target_type;
    const targetId = notification.target_id;

    if (!targetType || targetId == null) {
        return null;
    }

    if (targetType === 'order') {
        if (role === 'driver') {
            return '/driver/orders';
        }

        if (role === 'system_admin') {
            return `/admin/orders/${targetId}`;
        }
    }

    if (targetType === 'custom_order') {
        if (role === 'customer') {
            return `/customer/custom-orders/${targetId}`;
        }

        if (role === 'system_admin') {
            return `/admin/custom-orders/${targetId}`;
        }
    }

    if (targetType === 'incident' && role === 'system_admin') {
        return `/admin/incidents/${targetId}`;
    }

    return null;
}

export function openNotificationTarget(
    notification: InboxNotification,
    role: string | null | undefined,
): void {
    const path = resolveNotificationPath(notification, role);

    if (path) {
        router.visit(path);
    }
}
