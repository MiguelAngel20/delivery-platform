import { cn } from '@/lib/utils';
import type { BranchOption } from '@/apps/business/components/branch-multi-select';

type BranchSingleSelectProps = {
    name?: string;
    options: BranchOption[];
    value: number | null;
    onChange: (value: number | null) => void;
    disabled?: boolean;
    className?: string;
};

export function BranchSingleSelect({
    name = 'branch_ids',
    options,
    value,
    onChange,
    disabled = false,
    className,
}: BranchSingleSelectProps) {
    return (
        <div className={cn('space-y-2', className)}>
            {value !== null ? (
                <input type="hidden" name={`${name}[]`} value={value} />
            ) : null}
            {options.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No hay sucursales disponibles.
                </p>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                    {options.map((branch) => {
                        const checked = value === branch.id;

                        return (
                            <label
                                key={branch.id}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded-md border border-border bg-surface px-3 py-2 text-sm text-foreground',
                                    checked && 'border-primary bg-primary/5',
                                    disabled && 'cursor-not-allowed opacity-60',
                                )}
                            >
                                <input
                                    type="radio"
                                    name={`${name}_picker`}
                                    className="size-4 border-input"
                                    checked={checked}
                                    disabled={disabled}
                                    onChange={() => onChange(branch.id)}
                                />
                                <span>{branch.name}</span>
                            </label>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
