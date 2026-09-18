import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Link } from '@inertiajs/react';
import customer from '@/routes/customer';

export type ActivitySuspensionShared = {
    active: boolean;
    reason: string;
    reason_label: string;
    title: string;
    body: string;
    footnote: string;
    image_url: string;
    active_order_message: string | null;
    active_orders: Array<{
        id: number;
        order_number: string;
        status: string;
        status_label: string;
    }>;
};

export function ActivitySuspensionModal() {
    const { activitySuspension } = usePage().props as {
        activitySuspension?: ActivitySuspensionShared | null;
    };
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (activitySuspension?.active) {
            setOpen(true);
        } else {
            setOpen(false);
        }
    }, [activitySuspension?.active, activitySuspension?.reason]);

    if (!activitySuspension?.active) {
        return null;
    }

    const hasActiveOrders = activitySuspension.active_orders.length > 0;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                // Only allow closing via Accept; backdrop still closes for UX.
                setOpen(next);
            }}
        >
            <DialogContent className="max-h-[95dvh] overflow-y-auto sm:max-w-md">
                <DialogHeader className="space-y-3 text-center sm:text-center">
                    <img
                        src={activitySuspension.image_url}
                        alt=""
                        className="mx-auto h-32 w-full max-w-[260px] object-contain"
                    />
                    <DialogTitle className="text-xl text-navy">
                        {activitySuspension.title}
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed text-muted-foreground">
                        {activitySuspension.body}
                    </DialogDescription>
                </DialogHeader>

                <p className="rounded-xl bg-muted/60 px-3 py-2.5 text-center text-sm text-foreground">
                    {activitySuspension.footnote}
                </p>

                {hasActiveOrders ? (
                    <div className="space-y-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-3 text-sm text-amber-950">
                        {activitySuspension.active_order_message ? (
                            <p className="leading-relaxed">
                                {activitySuspension.active_order_message}
                            </p>
                        ) : null}
                        <ul className="space-y-1.5">
                            {activitySuspension.active_orders.map((order) => (
                                <li key={order.id}>
                                    <Link
                                        href={customer.orders.show(order.id)}
                                        className="flex items-center justify-between gap-2 rounded-lg bg-white/70 px-2.5 py-2 font-medium underline-offset-2 hover:underline"
                                        onClick={() => setOpen(false)}
                                    >
                                        <span>#{order.order_number}</span>
                                        <span className="text-xs font-normal text-amber-900/80">
                                            {order.status_label}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ) : null}

                <Button
                    type="button"
                    className="min-h-11 w-full"
                    onClick={() => setOpen(false)}
                >
                    Aceptar
                </Button>
            </DialogContent>
        </Dialog>
    );
}
