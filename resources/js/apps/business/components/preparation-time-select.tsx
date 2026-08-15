import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

export const PREPARATION_TIME_OPTIONS = [
    { value: '10', label: '10 minutos' },
    { value: '15', label: '15 minutos' },
    { value: '20', label: '20 minutos' },
    { value: '30', label: '30 minutos' },
    { value: '45', label: '45 minutos' },
] as const;

type PreparationTimeSelectProps = Omit<
    ComponentProps<'select'>,
    'children'
> & {
    label?: string;
};

export function PreparationTimeSelect({
    label = 'Tiempo estimado de preparación',
    className,
    id,
    ...props
}: PreparationTimeSelectProps) {
    const selectId = id ?? 'preparation-time';

    return (
        <div className="flex flex-col gap-1.5">
            <label
                htmlFor={selectId}
                className="text-sm font-medium text-[#0F172A]"
            >
                {label}
            </label>
            <select
                id={selectId}
                className={cn(
                    'border-input bg-background text-foreground focus-visible:border-ring focus-visible:ring-ring/50 h-10 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]',
                    className,
                )}
                {...props}
            >
                <option value="">Seleccionar</option>
                {PREPARATION_TIME_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </div>
    );
}
