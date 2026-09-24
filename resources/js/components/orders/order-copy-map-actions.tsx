import { Check, Copy, MapPin } from 'lucide-react';
import { useState } from 'react';
import { notify } from '@/components/feedback/toast';
import { Button } from '@/components/ui/button';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

type OrderCopyButtonProps = {
    label?: string;
    ariaLabel: string;
    successMessage: string;
    getCopyText: () => string;
    className?: string;
};

export function OrderCopyButton({
    label = 'Copiar',
    ariaLabel,
    successMessage,
    getCopyText,
    className,
}: OrderCopyButtonProps) {
    const [, copy] = useClipboard();
    const [justCopied, setJustCopied] = useState(false);

    return (
        <Button
            type="button"
            variant="outline"
            size="sm"
            className={cn('gap-1.5', className)}
            aria-label={ariaLabel}
            title={ariaLabel}
            onClick={() => {
                void copy(getCopyText()).then((copied) => {
                    if (copied) {
                        setJustCopied(true);
                        notify.success(successMessage);
                        window.setTimeout(() => setJustCopied(false), 2000);
                    } else {
                        notify.error('No se pudo copiar la información');
                    }
                });
            }}
        >
            {justCopied ? (
                <Check className="size-4 text-success" />
            ) : (
                <Copy className="size-4" />
            )}
            <span className="hidden sm:inline">
                {justCopied ? 'Copiado' : label}
            </span>
        </Button>
    );
}

type OrderCopyMapActionsProps = {
    mapsUrl?: string | null;
    mapsLabel?: string;
    copyLabel?: string;
    copyAriaLabel: string;
    successMessage: string;
    getCopyText: () => string;
    className?: string;
};

export function OrderCopyMapActions({
    mapsUrl = null,
    mapsLabel = 'Abrir ubicación',
    copyLabel = 'Copiar',
    copyAriaLabel,
    successMessage,
    getCopyText,
    className,
}: OrderCopyMapActionsProps) {
    const resolvedMapsUrl = mapsUrl?.trim() || null;

    return (
        <div className={cn('flex shrink-0 items-center gap-1.5', className)}>
            {resolvedMapsUrl ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="size-8"
                    asChild
                >
                    <a
                        href={resolvedMapsUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label={mapsLabel}
                        title={mapsLabel}
                    >
                        <MapPin className="size-4" />
                    </a>
                </Button>
            ) : null}
            <OrderCopyButton
                label={copyLabel}
                ariaLabel={copyAriaLabel}
                successMessage={successMessage}
                getCopyText={getCopyText}
            />
        </div>
    );
}
