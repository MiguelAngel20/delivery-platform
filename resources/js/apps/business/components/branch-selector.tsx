import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useMemo, useState } from 'react';
import { cn } from '@/lib/utils';
import type { BusinessContext } from '@/types/business';

type BranchSelectorProps = {
    className?: string;
};

export function BranchSelector({ className }: BranchSelectorProps) {
    const { businessContext } = usePage().props as {
        businessContext: BusinessContext | null;
    };
    const [open, setOpen] = useState(false);

    const currentBranch = useMemo(() => {
        if (!businessContext) {
            return null;
        }

        return (
            businessContext.branches.find(
                (branch) => branch.id === businessContext.current_branch_id,
            ) ?? businessContext.branches[0] ?? null
        );
    }, [businessContext]);

    if (!businessContext || businessContext.branches.length === 0) {
        return null;
    }

    return (
        <div className={cn('relative', className)}>
            <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-md border border-[#E2E8F0] bg-white px-3 py-1.5 text-sm font-medium text-navy"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
            >
                <span className="max-w-40 truncate">
                    {currentBranch?.name ?? 'Sucursal'}
                </span>
                <ChevronDown className="size-4 opacity-70" />
            </button>
            {open ? (
                <div className="absolute right-0 z-30 mt-2 min-w-48 rounded-md border border-[#E2E8F0] bg-white py-1 shadow-md">
                    {businessContext.branches.map((branch) => (
                        <button
                            key={branch.id}
                            type="button"
                            className={cn(
                                'block w-full px-3 py-2 text-left text-sm text-navy hover:bg-[#F8FAFC]',
                                branch.id === currentBranch?.id &&
                                    'bg-[#F8FAFC] font-medium',
                            )}
                            onClick={() => setOpen(false)}
                        >
                            {branch.name}
                        </button>
                    ))}
                </div>
            ) : null}
        </div>
    );
}
