import type { ReactNode } from 'react';
import { EmptyState } from '@/components/feedback/empty-state';
import { cn } from '@/lib/utils';

type StorefrontEmptyNoticeProps = {
    title: string;
    description?: string;
    icon?: ReactNode;
    className?: string;
};

/**
 * Compact empty panel for storefront home sections (promos, businesses).
 */
export function StorefrontEmptyNotice({
    title,
    description,
    icon,
    className,
}: StorefrontEmptyNoticeProps) {
    return (
        <EmptyState
            title={title}
            description={description}
            icon={icon}
            className={cn(
                'border-border/80 bg-muted/30 px-4 py-8 shadow-none',
                className,
            )}
        />
    );
}
