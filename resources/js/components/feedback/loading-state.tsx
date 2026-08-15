import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type LoadingStateProps = {
    label?: string;
    className?: string;
};

export function LoadingState({
    label = 'Cargando…',
    className,
}: LoadingStateProps) {
    return (
        <div
            role="status"
            className={cn(
                'flex flex-col items-center justify-center gap-3 py-12 text-muted-foreground',
                className,
            )}
        >
            <Spinner className="size-6" />
            <p className="text-sm">{label}</p>
        </div>
    );
}
