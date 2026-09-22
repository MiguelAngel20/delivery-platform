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
import { formatMoney } from '@/lib/money';

export type CommissionDebtShared = {
    blocked: boolean;
    amount: string;
    message: string;
};

export function CommissionDebtModal() {
    const { driver } = usePage().props as {
        driver?: {
            commissionDebt?: CommissionDebtShared | null;
        } | null;
    };
    const debt = driver?.commissionDebt ?? null;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setOpen(Boolean(debt?.blocked));
    }, [debt?.blocked, debt?.amount]);

    if (!debt?.blocked) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader className="space-y-2 text-center sm:text-center">
                    <DialogTitle className="text-xl text-navy">
                        Comisión pendiente
                    </DialogTitle>
                    <DialogDescription className="text-sm leading-relaxed text-muted-foreground">
                        {debt.message}
                    </DialogDescription>
                </DialogHeader>

                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-center">
                    <p className="text-xs font-medium uppercase tracking-wide text-amber-800">
                        Monto a liquidar
                    </p>
                    <p className="mt-1 text-2xl font-semibold text-amber-950">
                        {formatMoney(debt.amount)}
                    </p>
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Hasta que el administrador verifique tu pago, no podrás
                    aceptar nuevos pedidos.
                </p>

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
