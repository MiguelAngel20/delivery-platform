import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type SwitchRestaurantDialogProps = {
    open: boolean;
    currentRestaurant?: string | null;
    nextRestaurant?: string;
    onCancel: () => void;
    onConfirm: () => void;
};

export function SwitchRestaurantDialog({
    open,
    currentRestaurant,
    nextRestaurant,
    onCancel,
    onConfirm,
}: SwitchRestaurantDialogProps) {
    return (
        <Dialog open={open} onOpenChange={(next) => !next && onCancel()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        Ya tienes productos de otro establecimiento
                    </DialogTitle>
                    <DialogDescription>
                        Tu carrito actual es de{' '}
                        {currentRestaurant ?? 'otro lugar'}.
                        {nextRestaurant
                            ? ` ¿Deseas vaciarlo y continuar con ${nextRestaurant}?`
                            : ' ¿Deseas vaciar tu carrito y continuar?'}
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2 sm:justify-stretch">
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 flex-1"
                        onClick={onCancel}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="button"
                        className="min-h-11 flex-1"
                        onClick={onConfirm}
                    >
                        Cambiar establecimiento
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
