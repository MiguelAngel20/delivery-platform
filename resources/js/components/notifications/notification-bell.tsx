import { usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useNotificationInbox } from '@/hooks/use-notification-inbox';
import {
    openNotificationTarget,
    type InboxNotification,
} from '@/lib/notifications/helpers';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { IconButton } from '@/components/ui/icon-button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { Auth } from '@/types';

function formatWhen(value?: string | null): string {
    if (!value) {
        return '';
    }

    try {
        return new Intl.DateTimeFormat('es', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
}

type NotificationBellProps = {
    className?: string;
    compact?: boolean;
};

export function NotificationBell({
    className,
    compact = false,
}: NotificationBellProps) {
    const { auth } = usePage().props as { auth: Auth };
    const {
        open,
        setOpen,
        openPanel,
        items,
        unreadCount,
        loading,
        markAsRead,
        markAllAsRead,
        loadMore,
        hasMore,
    } = useNotificationInbox();

    if (!auth.user) {
        return null;
    }

    const onSelect = async (item: InboxNotification) => {
        if (!item.read_at) {
            await markAsRead(item.id);
        }

        openNotificationTarget(item, auth.user?.role);
        setOpen(false);
    };

    return (
        <>
            <span className={cn('relative', className)}>
                <IconButton
                    type="button"
                    variant="ghost"
                    label="Notificaciones"
                    className={cn('text-foreground', compact && 'size-9')}
                    onClick={() => void openPanel()}
                >
                    <Bell className={compact ? 'size-4' : undefined} />
                </IconButton>
                {unreadCount > 0 ? (
                    <span className="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-danger text-[10px] font-semibold text-white">
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                ) : null}
            </span>

            <Sheet open={open} onOpenChange={setOpen}>
                <SheetContent side="right" className="w-full sm:max-w-md">
                    <SheetHeader className="gap-3">
                        <SheetTitle>Notificaciones</SheetTitle>
                        {unreadCount > 0 ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-fit"
                                onClick={() => void markAllAsRead()}
                            >
                                Marcar todas como leídas
                            </Button>
                        ) : null}
                    </SheetHeader>

                    <div className="mt-4 flex flex-1 flex-col gap-2 overflow-y-auto pb-6">
                        {loading && items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Cargando…
                            </p>
                        ) : null}

                        {!loading && items.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No tienes notificaciones.
                            </p>
                        ) : null}

                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => void onSelect(item)}
                                className={cn(
                                    'rounded-lg border border-border px-3 py-3 text-left transition hover:bg-muted/40',
                                    !item.read_at && 'border-primary/30 bg-primary/5',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <p className="text-sm font-semibold text-navy">
                                        {item.title}
                                    </p>
                                    {!item.read_at ? (
                                        <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                    ) : null}
                                </div>
                                {item.body ? (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {item.body}
                                    </p>
                                ) : null}
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {formatWhen(item.created_at)}
                                </p>
                            </button>
                        ))}

                        {hasMore ? (
                            <Button
                                type="button"
                                variant="ghost"
                                disabled={loading}
                                onClick={() => void loadMore()}
                            >
                                Cargar más
                            </Button>
                        ) : null}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
