import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type StatusTone =
    | 'neutral'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'primary';

const toneClasses: Record<StatusTone, string> = {
    neutral: 'border-border bg-secondary text-secondary-foreground',
    success: 'border-success/20 bg-success/10 text-success',
    warning: 'border-warning/20 bg-warning/10 text-warning-foreground',
    danger: 'border-danger/20 bg-danger/10 text-danger',
    info: 'border-info/20 bg-info/10 text-info',
    primary: 'border-primary/20 bg-primary/10 text-primary',
};

type StatusBadgeProps = {
    children: ReactNode;
    tone?: StatusTone;
    className?: string;
};

export function StatusBadge({
    children,
    tone = 'neutral',
    className,
}: StatusBadgeProps) {
    return (
        <span
            data-slot="status-badge"
            className={cn(
                'inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                toneClasses[tone],
                className,
            )}
        >
            {children}
        </span>
    );
}
