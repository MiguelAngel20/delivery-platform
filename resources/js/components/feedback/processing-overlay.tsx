import type { ReactNode } from 'react';
import { LoadingDialog } from '@/components/feedback/loading-dialog';

type ProcessingOverlayProps = {
    open: boolean;
    title?: string;
    description?: string;
    className?: string;
    children?: ReactNode;
};

/** @deprecated Prefer `LoadingDialog` for new UI. Same overlay behavior. */
export function ProcessingOverlay({
    open,
    title = 'Procesando…',
    description,
    className,
    children,
}: ProcessingOverlayProps) {
    return (
        <LoadingDialog
            open={open}
            title={title}
            description={description}
            className={className}
        >
            {children}
        </LoadingDialog>
    );
}
