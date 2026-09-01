import type { ProductOptionGroupDraft } from '@/components/catalog/product-option-group-types';
import {
    SECTION_CONFIG,
    SECTION_ORDER,
} from '@/components/catalog/product-option-groups-config';

type ProductOptionGroupsReadonlyProps = {
    groups: ProductOptionGroupDraft[];
    loading?: boolean;
};

export function ProductOptionGroupsReadonly({
    groups,
    loading = false,
}: ProductOptionGroupsReadonlyProps) {
    if (loading) {
        return (
            <div className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm text-muted-foreground">
                Cargando personalización del producto…
            </div>
        );
    }

    if (groups.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-border px-4 py-3 text-sm text-muted-foreground">
                Este producto no tiene personalización configurada.
            </div>
        );
    }

    return (
        <div className="space-y-3 rounded-lg border border-border bg-muted/20 p-4">
            <p className="text-sm font-medium text-foreground">
                Personalización del producto
            </p>
            {SECTION_ORDER.map((type) => {
                const group = groups.find((entry) => entry.type === type);

                if (!group) {
                    return null;
                }

                const config = SECTION_CONFIG[type];
                const optionNames = group.options
                    .map((option) => option.name.trim())
                    .filter((name) => name !== '');

                if (optionNames.length === 0) {
                    return null;
                }

                return (
                    <div key={type} className="space-y-1">
                        <p className="text-sm font-medium text-foreground">
                            {config.label}
                        </p>
                        {config.showLimits ? (
                            <p className="text-xs text-muted-foreground">
                                Elige entre {group.min_selection} y{' '}
                                {group.max_selection}
                                {group.is_required
                                    ? ' (obligatorio)'
                                    : ' (opcional)'}
                            </p>
                        ) : null}
                        <ul className="list-inside list-disc text-sm text-muted-foreground">
                            {optionNames.map((name) => (
                                <li key={name}>{name}</li>
                            ))}
                        </ul>
                    </div>
                );
            })}
        </div>
    );
}
