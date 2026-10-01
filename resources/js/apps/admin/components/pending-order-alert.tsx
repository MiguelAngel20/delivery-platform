import { Link, usePage } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePrivateChannelEvents } from '@/hooks/realtime/use-private-channel-events';
import { canAdmin, type AdminAccess } from '@/lib/admin-access';
import {
    playAdminPendingChime,
    readAdminChimeVolume,
    setAdminChimeVolume,
    unlockAdminOrderChime,
} from '@/lib/admin/pending-order-chime';
import {
    readSeenPendingOrderIds,
    rememberSeenPendingOrders,
} from '@/lib/admin/pending-order-seen';
import { showBrowserNotification } from '@/lib/push/browser-notification';
import type { OrderRealtimePayload } from '@/lib/realtime/types';
import { index, show } from '@/routes/admin/orders';

type PendingOrder = {
    id: number;
    order_number: string;
};

const REPEAT_MS = 10_000;
const announcedOrderIds = new Set<number>();

function isViewingOrder(url: string, orderNumber: string): boolean {
    const path = decodeURIComponent(url.split('?')[0] ?? '');

    return path.endsWith(`/admin/orders/${orderNumber}`);
}

function sameIds(left: number[], right: number[]): boolean {
    if (left.length !== right.length) {
        return false;
    }

    return left.every((id, index) => id === right[index]);
}

export function PendingOrderAlert() {
    const page = usePage();
    const adminAccess = page.props.adminAccess as AdminAccess | null;
    const shared = (
        (page.props.adminPendingOrders as PendingOrder[] | undefined) ?? []
    ).filter((order) => order.id > 0 && order.order_number !== '');
    const [liveOrders, setLiveOrders] = useState<PendingOrder[]>([]);
    const [clearedIds, setClearedIds] = useState<number[]>([]);
    const [seenIds, setSeenIds] = useState<number[]>(() =>
        readSeenPendingOrderIds(),
    );
    const [soundReady, setSoundReady] = useState(false);
    const [volume, setVolume] = useState(() => readAdminChimeVolume());
    const soundUnlocked = useRef(false);

    const canViewOrders = canAdmin(adminAccess, 'orders');

    usePrivateChannelEvents({
        channels: ['admin'],
        events: ['.OrderCreated', '.OrderStatusChanged'],
        enabled: canViewOrders,
        onEvent: (event: string, payload: OrderRealtimePayload) => {
            if (
                event === '.OrderCreated' &&
                payload.status === 'pending_platform'
            ) {
                setLiveOrders((current) => {
                    if (current.some((order) => order.id === payload.order_id)) {
                        return current;
                    }

                    return [
                        {
                            id: payload.order_id,
                            order_number: payload.order_number,
                        },
                        ...current,
                    ];
                });
                setClearedIds((current) =>
                    current.filter((id) => id !== payload.order_id),
                );

                return;
            }

            if (
                event === '.OrderStatusChanged' &&
                payload.status !== 'pending_platform'
            ) {
                setClearedIds((current) =>
                    current.includes(payload.order_id)
                        ? current
                        : [...current, payload.order_id],
                );
                setLiveOrders((current) =>
                    current.filter((order) => order.id !== payload.order_id),
                );
            }
        },
    });

    const pending = useMemo(() => {
        const hidden = new Set([...seenIds, ...clearedIds]);
        const byId = new Map<number, PendingOrder>();

        for (const order of [...liveOrders, ...shared]) {
            if (!hidden.has(order.id)) {
                byId.set(order.id, order);
            }
        }

        return [...byId.values()];
    }, [clearedIds, liveOrders, seenIds, shared]);

    const unseen = useMemo(
        () =>
            pending.filter(
                (order) => !isViewingOrder(page.url, order.order_number),
            ),
        [page.url, pending],
    );

    const unseenRef = useRef(unseen);
    unseenRef.current = unseen;

    useEffect(() => {
        const openIds = pending
            .filter((order) => isViewingOrder(page.url, order.order_number))
            .map((order) => order.id);

        if (openIds.length === 0) {
            return;
        }

        setSeenIds((current) => {
            const next = rememberSeenPendingOrders([...current, ...openIds]);

            return sameIds(current, next) ? current : next;
        });
    }, [page.url, pending]);

    useEffect(() => {
        const unlock = () => {
            if (soundUnlocked.current) {
                return;
            }

            void unlockAdminOrderChime().then((ready) => {
                if (!ready || soundUnlocked.current) {
                    return;
                }

                soundUnlocked.current = true;
                setSoundReady(true);

                if (unseenRef.current.length > 0) {
                    playAdminPendingChime();
                }
            });
        };

        window.addEventListener('pointerdown', unlock);
        window.addEventListener('keydown', unlock);

        return () => {
            window.removeEventListener('pointerdown', unlock);
            window.removeEventListener('keydown', unlock);
        };
    }, []);

    const unseenKey = unseen.map((order) => order.id).join(',');

    useEffect(() => {
        const current = unseenRef.current;

        if (current.length === 0) {
            return;
        }

        const fresh = current.filter(
            (order) => !announcedOrderIds.has(order.id),
        );

        for (const order of fresh) {
            announcedOrderIds.add(order.id);
        }

        if (fresh.length > 0) {
            playAdminPendingChime();

            if (!document.hasFocus()) {
                const first = fresh[0];
                const body =
                    fresh.length === 1
                        ? `Llegó el pedido #${first.order_number} y hay que confirmarlo.`
                        : `Llegaron ${fresh.length} pedidos y hay que confirmarlos.`;

                void showBrowserNotification(
                    'Pedido por confirmar',
                    body,
                    `admin-pending-order:${first.id}`,
                );
            }
        }

        const timer = window.setInterval(() => {
            if (unseenRef.current.length > 0) {
                playAdminPendingChime();
            }
        }, REPEAT_MS);

        return () => window.clearInterval(timer);
    }, [unseenKey]);

    if (!canViewOrders || unseen.length === 0) {
        return null;
    }

    const primary = unseen[0];
    const title =
        unseen.length === 1
            ? `Llegó el pedido #${primary.order_number} por confirmar`
            : `Tienes ${unseen.length} pedidos por confirmar`;
    const href =
        unseen.length === 1
            ? show.url(primary.order_number)
            : index.url({ query: { filter: 'pending' } });

    return (
        <div
            className="sticky top-16 z-30 px-4 pt-3 md:px-6"
            data-pending-order-alert
        >
            <Alert variant="warning">
                <BellRing />
                <AlertTitle>{title}</AlertTitle>
                <AlertDescription>
                    {soundReady
                        ? 'El timbre se repite hasta que abras el pedido o marques que ya lo viste.'
                        : 'Toca la página una vez para activar el timbre. Seguirá sonando hasta que abras el pedido.'}
                    <div className="mt-3 flex max-w-xs items-center gap-3 text-foreground">
                        <label
                            htmlFor="admin-order-chime-volume"
                            className="shrink-0 text-sm font-medium"
                        >
                            Volumen
                        </label>
                        <input
                            id="admin-order-chime-volume"
                            type="range"
                            min={0}
                            max={100}
                            step={5}
                            value={volume}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-valuenow={volume}
                            aria-label="Volumen del timbre"
                            className="accent-primary h-2 w-full cursor-pointer"
                            onChange={(event) => {
                                const next = Number(event.target.value);
                                setVolume(next);
                                setAdminChimeVolume(next);
                            }}
                            onPointerUp={() => {
                                void unlockAdminOrderChime().then((ready) => {
                                    if (!ready) {
                                        return;
                                    }

                                    soundUnlocked.current = true;
                                    setSoundReady(true);
                                    playAdminPendingChime();
                                });
                            }}
                            onKeyUp={() => {
                                playAdminPendingChime();
                            }}
                        />
                        <span className="w-10 shrink-0 text-sm tabular-nums">
                            {volume}%
                        </span>
                    </div>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <Button size="sm" asChild>
                            <Link href={href}>
                                {unseen.length === 1
                                    ? 'Ver pedido'
                                    : 'Ver pedidos'}
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            type="button"
                            onClick={() => {
                                setSeenIds((current) =>
                                    rememberSeenPendingOrders([
                                        ...current,
                                        ...unseen.map((order) => order.id),
                                    ]),
                                );
                            }}
                        >
                            Ya lo vi
                        </Button>
                    </div>
                </AlertDescription>
            </Alert>
        </div>
    );
}
