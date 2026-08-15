import { cn } from '@/lib/utils';

export type BranchOption = {
    id: number;
    name: string;
    status?: string;
    status_label?: string;
};

type BranchMultiSelectProps = {
    name?: string;
    options: BranchOption[];
    value: number[];
    onChange: (value: number[]) => void;
    disabled?: boolean;
    className?: string;
};

export function BranchMultiSelect({
    name = 'branch_ids',
    options,
    value,
    onChange,
    disabled = false,
    className,
}: BranchMultiSelectProps) {
    const toggle = (branchId: number) => {
        if (value.includes(branchId)) {
            onChange(value.filter((id) => id !== branchId));

            return;
        }

        onChange([...value, branchId]);
    };

    return (
        <div className={cn('space-y-2', className)}>
            {value.map((id) => (
                <input key={id} type="hidden" name={`${name}[]`} value={id} />
            ))}
            {options.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No hay sucursales disponibles.
                </p>
            ) : (
                <div className="grid gap-2 sm:grid-cols-2">
                    {options.map((branch) => {
                        const checked = value.includes(branch.id);

                        return (
                            <label
                                key={branch.id}
                                className={cn(
                                    'flex cursor-pointer items-center gap-2 rounded-md border border-[#E2E8F0] bg-white px-3 py-2 text-sm text-navy',
                                    checked && 'border-primary bg-primary/5',
                                    disabled && 'cursor-not-allowed opacity-60',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    className="size-4 rounded border-input"
                                    checked={checked}
                                    disabled={disabled}
                                    onChange={() => toggle(branch.id)}
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
