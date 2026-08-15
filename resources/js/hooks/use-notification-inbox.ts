import { echo, echoIsConfigured } from '@laravel/echo-react';
import { usePage } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
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

    useEffect(() => {
        setUnreadCount(shared?.unread_count ?? 0);
    }, [shared?.unread_count]);

    useEffect(() => {
        if (!auth.user || !echoIsConfigured()) {
            return;
        }

        const channelName = `user.${auth.user.id}.notifications`;
        const channel = echo().private(channelName);

        channel.listen('.UnreadNotificationsUpdated', (payload: { unread_count: number }) => {
            setUnreadCount(payload.unread_count);
        });

        return () => {
            echo().leave(channelName);
        };
    }, [auth.user?.id]);

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
