import { applyStorefrontCategoryFilter } from '@/apps/storefront/components/category-card';
import type { MockCategory } from '@/apps/storefront/mocks';
import { cn } from '@/lib/utils';

type MobileCategoryTabsProps = {
    categories: MockCategory[];
    selectedSlug?: string | null;
    className?: string;
};

export function MobileCategoryTabs({
    categories,
    selectedSlug = null,
    className,
}: MobileCategoryTabsProps) {
    if (categories.length === 0) {
        return null;
    }

    return (
        <nav
            aria-label="Filtrar por giro"
            className={cn(
                'min-w-0 max-w-full border-b border-border bg-surface md:hidden',
                className,
            )}
        >
            <div className="flex min-w-0 max-w-full gap-5 overflow-x-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <button
                    type="button"
                    onClick={() => applyStorefrontCategoryFilter(null)}
                    aria-pressed={selectedSlug === null}
                    className={cn(
                        'shrink-0 border-b-2 px-0.5 py-2.5 text-sm whitespace-nowrap transition-colors',
                        selectedSlug === null
                            ? 'border-navy font-semibold text-navy'
                            : 'border-transparent text-muted-foreground hover:text-navy',
                    )}
                >
                    Todo
                </button>
                {categories.map((category) => {
                    const selected = selectedSlug === category.slug;

                    return (
                        <button
                            key={category.id}
                            type="button"
                            onClick={() =>
                                applyStorefrontCategoryFilter(
                                    selected ? null : category.slug,
                                )
                            }
                            aria-pressed={selected}
                            className={cn(
                                'shrink-0 border-b-2 px-0.5 py-2.5 text-sm whitespace-nowrap transition-colors',
                                selected
                                    ? 'border-navy font-semibold text-navy'
                                    : 'border-transparent text-muted-foreground hover:text-navy',
                            )}
                        >
                            {category.name}
                        </button>
                    );
                })}
            </div>
        </nav>
    );
}
