import { router } from '@inertiajs/react';
import type { MockCategory } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';
import { home } from '@/routes';

type CategoryCardProps = {
    category: MockCategory;
    className?: string;
    selected?: boolean;
};

export function applyStorefrontCategoryFilter(slug: string | null): void {
    router.get(
        home.url({
            query: slug ? { category: slug } : {},
        }),
        {},
        {
            preserveScroll: true,
            replace: true,
        },
    );
}

export function CategoryCard({
    category,
    className,
    selected = false,
}: CategoryCardProps) {
    return (
        <button
            type="button"
            onClick={() =>
                applyStorefrontCategoryFilter(selected ? null : category.slug)
            }
            aria-pressed={selected}
            className={cn(
                'flex min-h-16 min-w-28 flex-col justify-center rounded-xl border border-border bg-surface px-3 py-3 text-left shadow-sm transition-colors',
                selected
                    ? 'border-primary bg-primary/5 ring-1 ring-primary'
                    : 'hover:border-primary/40 hover:bg-primary/5',
                className,
            )}
        >
            <span className="text-sm font-semibold text-navy">
                {category.name}
            </span>
        </button>
    );
}
