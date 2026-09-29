import { echoIsConfigured } from '@laravel/echo-react';
import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { listenOnPrivateChannel } from '@/hooks/realtime/private-channel-registry';
import { notify } from '@/components/feedback/toast';
import { showBrowserNotification } from '@/lib/push/browser-notification';
import type { InboxNotification } from '@/lib/notifications/helpers';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function jsonFetch(url: string, options: RequestInit = {}): Promise<Response> {
    return fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
            ...(options.headers ?? {}),
        },
        ...options,
    });
}

type PageProps = {
    auth: { user: { id: number } | null };
    notifications?: { unread_count?: number };
};

export function useNotificationInbox() {
    const { auth, notifications: shared } = usePage().props as PageProps;
    const [open, setOpen] = useState(false);
    const [items, setItems] = useState<InboxNotification[]>([]);
    const [unreadCount, setUnreadCount] = useState(
        shared?.unread_count ?? 0,
    );
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const openRef = useRef(open);
    openRef.current = open;

    useEffect(() => {
        setUnreadCount(shared?.unread_count ?? 0);
    }, [shared?.unread_count]);

    const load = useCallback(async (nextPage = 1, append = false) => {
        setLoading(true);

        try {
            const response = await jsonFetch(
                `/notifications/inbox?page=${nextPage}`,
            );

            if (!response.ok) {
                return;
            }

            const json = (await response.json()) as {
                unread_count: number;
                data: InboxNotification[];
                meta: { current_page: number; last_page: number };
            };

            setUnreadCount(json.unread_count);
            setPage(json.meta.current_page);
            setLastPage(json.meta.last_page);
            setItems((prev) =>
                append ? [...prev, ...json.data] : json.data,
            );
        } finally {
            setLoading(false);
        }
    }, []);

    const loadRef = useRef(load);
    loadRef.current = load;

    useEffect(() => {
        if (!auth.user || !echoIsConfigured()) {
            return;
        }

        return listenOnPrivateChannel(
            `user.${auth.user.id}.notifications`,
            ['.UnreadNotificationsUpdated'],
            (_eventName, payload) => {
                const data = payload as {
                    unread_count: number;
                    title?: string | null;
                    body?: string | null;
                };

                setUnreadCount(data.unread_count);

                const title = data.title?.trim();
                const body = data.body?.trim();

                if (title) {
                    notify.info(body ? `${title}. ${body}` : title);
                    void showBrowserNotification(title, body);
                }

                if (openRef.current) {
                    void loadRef.current(1, false);
                }
            },
        );
    }, [auth.user?.id]);

    const openPanel = useCallback(async () => {
        setOpen(true);
        await load(1, false);
    }, [load]);

    const markAsRead = useCallback(async (id: string) => {
        const response = await jsonFetch(`/notifications/${id}/read`, {
            method: 'POST',
        });

        if (!response.ok) {
            return;
        }

        const json = (await response.json()) as { unread_count: number };
        setUnreadCount(json.unread_count);
        setItems((prev) =>
            prev.map((item) =>
                item.id === id
                    ? { ...item, read_at: item.read_at ?? new Date().toISOString() }
                    : item,
            ),
        );
    }, []);

    const markAllAsRead = useCallback(async () => {
        const response = await jsonFetch('/notifications/read-all', {
            method: 'POST',
        });

        if (!response.ok) {
            return;
        }

        setUnreadCount(0);
        setItems((prev) =>
            prev.map((item) => ({
                ...item,
                read_at: item.read_at ?? new Date().toISOString(),
            })),
        );
    }, []);

    const loadMore = useCallback(async () => {
        if (page >= lastPage || loading) {
            return;
        }

        await load(page + 1, true);
    }, [load, page, lastPage, loading]);

    return {
        open,
        setOpen,
        openPanel,
        items,
        unreadCount,
        loading,
        markAsRead,
        markAllAsRead,
        loadMore,
        hasMore: page < lastPage,
    };
}
