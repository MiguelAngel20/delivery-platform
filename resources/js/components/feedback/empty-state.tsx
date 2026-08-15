import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    title: string;
    description?: string;
    icon?: ReactNode;
    action?: ReactNode;
    className?: string;
};

export function EmptyState({
    title,
    description,
    icon,
    action,
    className,
}: EmptyStateProps) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed bg-surface px-6 py-12 text-center',
                className,
            )}
        >
            {icon ? (
                <div className="text-muted-foreground [&_svg]:size-8">
                    {icon}
                </div>
            ) : null}
            <div className="space-y-1">
                <h3 className="text-base font-semibold text-foreground">
                    {title}
                </h3>
                {description ? (
                    <p className="max-w-sm text-sm text-muted-foreground">
                        {description}
                    </p>
                ) : null}
            </div>
            {action}
        </div>
    );
}
