import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type PageContainerProps = {
    children: ReactNode;
    className?: string;
};

export function PageContainer({ children, className }: PageContainerProps) {
    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8',
                className,
            )}
        >
            {children}
        </div>
    );
}

type PageHeaderProps = {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
};

export function PageHeader({
    title,
    description,
    actions,
    className,
}: PageHeaderProps) {
    return (
        <div
            className={cn(
                'flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between',
                className,
            )}
        >
            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight text-[#0F172A] md:text-3xl">
                    {title}
                </h1>
                {description ? (
                    <p className="max-w-2xl text-sm text-[#64748B]">
                        {description}
                    </p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}

type SectionProps = {
    title?: string;
    description?: string;
    children: ReactNode;
    className?: string;
};

export function Section({
    title,
    description,
    children,
    className,
}: SectionProps) {
    return (
        <section className={cn('flex flex-col gap-4', className)}>
            {title ? (
                <div className="space-y-1">
                    <h2 className="text-lg font-semibold text-navy">{title}</h2>
                    {description ? (
                        <p className="text-sm text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {children}
        </section>
    );
}

type ContentCardProps = {
    title?: string;
    description?: string;
    actions?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
};

export function ContentCard({
    title,
    description,
    actions,
    children,
    className,
    bodyClassName,
}: ContentCardProps) {
    return (
        <div
            className={cn(
                'rounded-xl border border-[#E2E8F0] bg-white text-[#0F172A] shadow-sm',
                className,
            )}
        >
            {(title || actions) && (
                <div className="flex items-start justify-between gap-3 border-b border-[#E2E8F0] px-4 py-4 md:px-5">
                    <div className="space-y-0.5">
                        {title ? (
                            <h2 className="text-base font-semibold text-[#0F172A]">
                                {title}
                            </h2>
                        ) : null}
                        {description ? (
                            <p className="text-sm text-[#64748B]">
                                {description}
                            </p>
                        ) : null}
                    </div>
                    {actions}
                </div>
            )}
            <div className={cn('p-4 md:p-5', bodyClassName)}>{children}</div>
        </div>
    );
}
