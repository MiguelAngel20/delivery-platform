import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label?: string;
    htmlFor?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    className?: string;
    children: ReactNode;
};

export function FormField({
    label,
    htmlFor,
    error,
    hint,
    required = false,
    className,
    children,
}: FormFieldProps) {
    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            {label ? (
                <Label htmlFor={htmlFor}>
                    {label}
                    {required ? (
                        <span aria-hidden="true" className="text-danger">
                            {' '}
                            *
                        </span>
                    ) : null}
                </Label>
            ) : null}
            {children}
            {hint && !error ? (
                <p className="text-sm text-muted-foreground">{hint}</p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
