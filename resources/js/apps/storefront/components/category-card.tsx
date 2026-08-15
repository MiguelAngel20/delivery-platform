import { Link } from '@inertiajs/react';
import type { MockCategory } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';
import categories from '@/routes/categories';

type CategoryCardProps = {
    category: MockCategory;
    className?: string;
};

export function CategoryCard({ category, className }: CategoryCardProps) {
    return (
        <Link
            href={categories.index.url({ query: { category: category.slug } })}
            className={cn(
                'flex min-h-16 min-w-28 flex-col justify-center rounded-xl border border-border bg-surface px-3 py-3 text-left shadow-sm',
                className,
            )}
        >
            <span className="text-sm font-semibold text-navy">
                {category.name}
            </span>
        </Link>
    );
}
