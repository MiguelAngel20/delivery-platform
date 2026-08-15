import type { MockAddress } from '@/apps/storefront/mocks';
import { StatusBadge } from '@/components/data-display/status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type AddressCardProps = {
    address: MockAddress;
    selected?: boolean;
    onSelect?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
    className?: string;
};

export function AddressCard({
    address,
    selected = false,
    onSelect,
    onEdit,
    onDelete,
    className,
}: AddressCardProps) {
    const showActions = onEdit !== undefined || onDelete !== undefined;

    return (
        <div
            className={cn(
                'w-full rounded-xl border bg-surface p-4 text-left shadow-sm',
                selected ? 'border-primary' : 'border-border',
                className,
            )}
        >
            <button
                type="button"
                onClick={onSelect}
                disabled={!onSelect}
                className={cn(
                    'w-full text-left',
                    onSelect ? 'cursor-pointer' : 'cursor-default',
                )}
            >
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="font-semibold text-navy">{address.label}</p>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {address.line}
                        </p>
                    </div>
                    {address.isDefault ? (
                        <StatusBadge tone="primary">Predeterminada</StatusBadge>
                    ) : null}
                </div>
            </button>

            {showActions ? (
                <div className="mt-3 flex gap-2">
                    {onEdit ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onEdit}
                        >
                            Editar
                        </Button>
                    ) : null}
                    {onDelete ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={onDelete}
                        >
                            Eliminar
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
