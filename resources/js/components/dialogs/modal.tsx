import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ModalProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children?: ReactNode;
    footer?: ReactNode;
};

export function Modal({
    open,
    onOpenChange,
    title,
    description,
    children,
    footer,
}: ModalProps) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                {children}
                {footer ? <DialogFooter>{footer}</DialogFooter> : null}
            </DialogContent>
        </Dialog>
    );
}

type ConfirmDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'primary' | 'danger';
    loading?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    variant = 'primary',
    loading = false,
    onConfirm,
}: ConfirmDialogProps) {
    return (
        <Modal
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            footer={
                <>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {cancelLabel}
                    </Button>
                    <Button
                        type="button"
                        variant={variant === 'danger' ? 'danger' : 'primary'}
                        loading={loading}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </>
            }
        />
    );
}

export function DeleteConfirmDialog({
    confirmLabel = 'Eliminar',
    title = 'Eliminar registro',
    ...props
}: Omit<ConfirmDialogProps, 'variant'>) {
    return (
        <ConfirmDialog
            {...props}
            title={title}
            confirmLabel={confirmLabel}
            variant="danger"
        />
    );
}
