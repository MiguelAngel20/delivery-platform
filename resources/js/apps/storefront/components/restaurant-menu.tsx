import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { ProductCard } from '@/apps/storefront/components/product-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type RestaurantMenuCategory = {
    id: number;
    name: string;
    description?: string | null;
    children: Array<{
        id: number;
        name: string;
        description?: string | null;
    }>;
};

export type RestaurantMenuProduct = {
    id: number | string;
    name: string;
    description: string;
    price: number;
    has_size_options?: boolean;
    image_url?: string | null;
    is_available?: boolean;
    product_category_id?: number | null;
    parent_category_id?: number | null;
    subcategory?: string | null;
    category: string;
};

type RestaurantMenuProps = {
    categories: RestaurantMenuCategory[];
    products: RestaurantMenuProduct[];
    canOrder: boolean;
    onAdd: (product: RestaurantMenuProduct) => void;
};

type MenuSection = {
    id: number | string;
    name: string;
    subsections: Array<{
        id: number | string | null;
        name: string | null;
        products: RestaurantMenuProduct[];
    }>;
};

function buildMenuSections(
    categories: RestaurantMenuCategory[],
    products: RestaurantMenuProduct[],
): MenuSection[] {
    const assigned = new Set<string>();

    const sections = categories
        .map((category): MenuSection => {
            const direct = products.filter(
                (product) =>
                    Number(product.product_category_id) === category.id,
            );
            direct.forEach((product) => assigned.add(String(product.id)));

            const subsections: MenuSection['subsections'] = [];

            if (direct.length > 0) {
                subsections.push({
                    id: null,
                    name: null,
                    products: direct,
                });
            }

            for (const child of category.children) {
                const childProducts = products.filter(
                    (product) =>
                        Number(product.product_category_id) === child.id,
                );

                if (childProducts.length === 0) {
                    continue;
                }

                childProducts.forEach((product) =>
                    assigned.add(String(product.id)),
                );
                subsections.push({
                    id: child.id,
                    name: child.name,
                    products: childProducts,
                });
            }

            return {
                id: category.id,
                name: category.name,
                subsections,
            };
        })
        .filter((section) => section.subsections.length > 0);

    const orphans = products.filter(
        (product) => !assigned.has(String(product.id)),
    );

    if (orphans.length > 0) {
        const byPath = orphans.reduce<Record<string, RestaurantMenuProduct[]>>(
            (groups, product) => {
                const key = product.category || 'Sin categoría';
                groups[key] ??= [];
                groups[key].push(product);

                return groups;
            },
            {},
        );

        for (const [name, items] of Object.entries(byPath)) {
            sections.push({
                id: `orphan-${name}`,
                name,
                subsections: [{ id: null, name: null, products: items }],
            });
        }
    }

    return sections;
}

function SectionBlock({
    section,
    canOrder,
    onAdd,
}: {
    section: MenuSection;
    canOrder: boolean;
    onAdd: (product: RestaurantMenuProduct) => void;
}) {
    const sectionId = String(section.id);

    return (
        <div className="space-y-3 md:space-y-4">
            <div className="space-y-1">
                <h3 className="text-base font-semibold text-navy md:text-lg">
                    {section.name}
                </h3>
                <div className="h-0.5 w-10 rounded-full bg-primary md:w-12" />
            </div>

            {section.subsections.map((subsection) => (
                <div
                    key={`${sectionId}-${subsection.id ?? 'root'}`}
                    className="space-y-2 md:space-y-3"
                >
                    {subsection.name ? (
                        <h4 className="text-sm font-medium text-muted-foreground md:text-base md:font-semibold md:text-navy">
                            {subsection.name}
                        </h4>
                    ) : null}
                    <div className="grid gap-2 sm:grid-cols-2 sm:gap-3 xl:grid-cols-3">
                        {subsection.products.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={{
                                    id: String(product.id),
                                    name: product.name,
                                    description: product.description,
                                    price: product.price,
                                    has_size_options: product.has_size_options,
                                    image_url: product.image_url,
                                }}
                                canOrder={
                                    canOrder && product.is_available !== false
                                }
                                onAdd={() => onAdd(product)}
                            />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}

function CategoryTabs({
    sections,
    activeSectionId,
    onSelect,
}: {
    sections: MenuSection[];
    activeSectionId: string;
    onSelect: (id: string) => void;
}) {
    const scrollerRef = useRef<HTMLDivElement>(null);
    const [canScrollLeft, setCanScrollLeft] = useState(false);
    const [canScrollRight, setCanScrollRight] = useState(false);

    const updateScrollHints = () => {
        const el = scrollerRef.current;

        if (!el) {
            return;
        }

        const maxScroll = el.scrollWidth - el.clientWidth;

        setCanScrollLeft(el.scrollLeft > 2);
        setCanScrollRight(maxScroll > 2 && el.scrollLeft < maxScroll - 2);
    };

    useEffect(() => {
        const el = scrollerRef.current;

        if (!el) {
            return;
        }

        updateScrollHints();

        const onScroll = () => updateScrollHints();
        const onWheel = (event: WheelEvent) => {
            const maxScroll = el.scrollWidth - el.clientWidth;

            if (maxScroll <= 0) {
                return;
            }

            const delta =
                Math.abs(event.deltaX) > Math.abs(event.deltaY)
                    ? event.deltaX
                    : event.deltaY;

            if (delta === 0) {
                return;
            }

            const atStart = el.scrollLeft <= 0;
            const atEnd = el.scrollLeft >= maxScroll - 1;

            if ((delta < 0 && atStart) || (delta > 0 && atEnd)) {
                return;
            }

            event.preventDefault();
            el.scrollLeft += delta;
        };

        el.addEventListener('scroll', onScroll, { passive: true });
        el.addEventListener('wheel', onWheel, { passive: false });

        const resizeObserver = new ResizeObserver(() => updateScrollHints());
        resizeObserver.observe(el);

        return () => {
            el.removeEventListener('scroll', onScroll);
            el.removeEventListener('wheel', onWheel);
            resizeObserver.disconnect();
        };
    }, [sections]);

    useEffect(() => {
        const el = scrollerRef.current;

        if (!el) {
            return;
        }

        const active = el.querySelector<HTMLElement>(
            `[data-category-id="${CSS.escape(activeSectionId)}"]`,
        );

        active?.scrollIntoView({
            behavior: 'smooth',
            inline: 'nearest',
            block: 'nearest',
        });
    }, [activeSectionId]);

    const scrollByDirection = (direction: -1 | 1) => {
        const el = scrollerRef.current;

        if (!el) {
            return;
        }

        el.scrollBy({
            left: direction * Math.max(160, el.clientWidth * 0.55),
            behavior: 'smooth',
        });
    };

    return (
        <div className="relative">
            {canScrollLeft ? (
                <>
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-y-0 left-0 z-10 w-8 bg-gradient-to-r from-background via-background/80 to-transparent md:w-10"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 left-0 z-20 size-7 -translate-y-1/2 rounded-full bg-surface/95 shadow-sm md:size-8"
                        aria-label="Ver categorías anteriores"
                        onClick={() => scrollByDirection(-1)}
                    >
                        <ChevronLeft className="size-4" />
                    </Button>
                </>
            ) : null}

            {canScrollRight ? (
                <>
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-y-0 right-0 z-10 w-8 bg-gradient-to-l from-background via-background/80 to-transparent md:w-10"
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        className="absolute top-1/2 right-0 z-20 size-7 -translate-y-1/2 rounded-full bg-surface/95 shadow-sm md:size-8"
                        aria-label="Ver más categorías"
                        onClick={() => scrollByDirection(1)}
                    >
                        <ChevronRight className="size-4" />
                    </Button>
                </>
            ) : null}

            <div
                ref={scrollerRef}
                className="flex gap-2 overflow-x-auto scroll-smooth px-1 pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:gap-2.5"
            >
                {sections.map((section) => {
                    const id = String(section.id);
                    const selected = activeSectionId === id;

                    return (
                        <button
                            key={id}
                            type="button"
                            data-category-id={id}
                            onClick={() => onSelect(id)}
                            aria-pressed={selected}
                            className={cn(
                                'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors md:px-4 md:py-2 md:text-sm',
                                selected
                                    ? 'border-navy bg-navy text-white'
                                    : 'border-border bg-secondary/70 text-navy hover:border-primary/40',
                            )}
                        >
                            {section.name}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export function RestaurantMenu({
    categories,
    products,
    canOrder,
    onAdd,
}: RestaurantMenuProps) {
    const sections = useMemo(
        () => buildMenuSections(categories, products),
        [categories, products],
    );
    const [activeSectionId, setActiveSectionId] = useState<string | null>(
        sections[0] ? String(sections[0].id) : null,
    );

    useEffect(() => {
        if (sections.length === 0) {
            setActiveSectionId(null);

            return;
        }

        const stillExists = sections.some(
            (section) => String(section.id) === activeSectionId,
        );

        if (!stillExists) {
            setActiveSectionId(String(sections[0].id));
        }
    }, [sections, activeSectionId]);

    const activeSection = useMemo(
        () =>
            sections.find((section) => String(section.id) === activeSectionId) ??
            sections[0] ??
            null,
        [sections, activeSectionId],
    );

    if (sections.length === 0 || activeSection === null) {
        return null;
    }

    return (
        <div className="space-y-4">
            <nav
                aria-label="Categorías del menú"
                className="sticky top-[4.25rem] z-20 -mx-1 border-b border-border/60 bg-background/95 px-1 pb-2 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:top-[5.25rem]"
            >
                <CategoryTabs
                    sections={sections}
                    activeSectionId={String(activeSection.id)}
                    onSelect={setActiveSectionId}
                />
            </nav>

            <SectionBlock
                section={activeSection}
                canOrder={canOrder}
                onAdd={onAdd}
            />
        </div>
    );
}
