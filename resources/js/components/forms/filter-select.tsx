import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type FilterSelectProps = ComponentProps<'select'> & {
    label: string;
};

export function FilterSelect({
    label,
    id,
    className,
    children,
    ...props
}: FilterSelectProps) {
    const selectId = id ?? props.name ?? label.toLowerCase().replace(/\s+/g, '-');

    return (
        <div className="flex min-w-40 flex-col gap-1.5">
            <label htmlFor={selectId} className="sr-only">
                {label}
            </label>
            <select
                id={selectId}
                className={cn(
                    'border-input bg-background text-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
                    className,
                )}
                {...props}
            >
                {children}
            </select>
        </div>
    );
}
