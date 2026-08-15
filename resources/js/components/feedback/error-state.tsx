import { AlertCircle } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

type ErrorStateProps = {
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
};

export function ErrorState({
    title = 'No se pudo cargar la información',
    description = 'Inténtalo de nuevo en unos momentos.',
    action,
    className,
}: ErrorStateProps) {
    return (
        <Alert variant="danger" className={cn(className)}>
            <AlertCircle />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription>
                <div className="flex flex-col items-start gap-3">
                    <p>{description}</p>
                    {action}
                </div>
            </AlertDescription>
        </Alert>
    );
}
