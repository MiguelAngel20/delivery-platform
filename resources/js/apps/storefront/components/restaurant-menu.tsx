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

type MenuSubsection = {
    id: number | string | null;
    name: string | null;
    products: RestaurantMenuProduct[];
};

type MenuSection = {
    id: number | string;
    name: string;
    subsections: MenuSubsection[];
};

type ScrollableTabItem = {
    id: string;
    label: string;
};

function subsectionKey(subsection: MenuSubsection): string {
    return String(subsection.id ?? 'root');
}

function subsectionLabel(subsection: MenuSubsection): string {
    return subsection.name ?? 'General';
}

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

            const subsections: MenuSubsection[] = [];

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

function ProductGrid({
    products,
    canOrder,
    onAdd,
}: {
    products: RestaurantMenuProduct[];
    canOrder: boolean;
    onAdd: (product: RestaurantMenuProduct) => void;
}) {
    return (
        <div className="grid gap-2 sm:grid-cols-2 sm:gap-3 xl:grid-cols-3">
            {products.map((product) => (
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
                    canOrder={canOrder && product.is_available !== false}
                    onAdd={() => onAdd(product)}
                />
            ))}
        </div>
    );
}

function ScrollableTabs({
    items,
    activeId,
    onSelect,
    previousLabel,
    nextLabel,
}: {
    items: ScrollableTabItem[];
    activeId: string;
    onSelect: (id: string) => void;
    previousLabel: string;
    nextLabel: string;
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
    }, [items]);

    useEffect(() => {
        const el = scrollerRef.current;

        if (!el) {
            return;
        }

        const active = el.querySelector<HTMLElement>(
            `[data-tab-id="${CSS.escape(activeId)}"]`,
        );

        active?.scrollIntoView({
            behavior: 'smooth',
            inline: 'nearest',
            block: 'nearest',
        });
    }, [activeId]);

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

    const needsScrollControls = canScrollLeft || canScrollRight;

    return (
        <div className="flex items-center gap-1 md:gap-1.5">
            {needsScrollControls ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={!canScrollLeft}
                    className={cn(
                        'size-7 shrink-0 rounded-full bg-surface shadow-sm md:size-8',
                        !canScrollLeft && 'invisible',
                    )}
                    aria-label={previousLabel}
                    onClick={() => scrollByDirection(-1)}
                >
                    <ChevronLeft className="size-4" />
                </Button>
            ) : null}

            <div
                ref={scrollerRef}
                className="flex min-w-0 flex-1 gap-2 overflow-x-auto scroll-smooth pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:gap-2.5"
            >
                {items.map((item) => {
                    const selected = activeId === item.id;

                    return (
                        <button
                            key={item.id}
                            type="button"
                            data-tab-id={item.id}
                            onClick={() => onSelect(item.id)}
                            aria-pressed={selected}
                            className={cn(
                                'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors md:px-4 md:py-2 md:text-sm',
                                selected
                                    ? 'border-navy bg-navy text-white'
                                    : 'border-border bg-secondary/70 text-navy hover:border-primary/40',
                            )}
                        >
                            {item.label}
                        </button>
                    );
                })}
            </div>

            {needsScrollControls ? (
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    disabled={!canScrollRight}
                    className={cn(
                        'size-7 shrink-0 rounded-full bg-surface shadow-sm md:size-8',
                        !canScrollRight && 'invisible',
                    )}
                    aria-label={nextLabel}
                    onClick={() => scrollByDirection(1)}
                >
                    <ChevronRight className="size-4" />
                </Button>
            ) : null}
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
    const [activeSubsectionId, setActiveSubsectionId] = useState<string | null>(
        sections[0]?.subsections[0]
            ? subsectionKey(sections[0].subsections[0])
            : null,
    );

    useEffect(() => {
        if (sections.length === 0) {
            setActiveSectionId(null);
            setActiveSubsectionId(null);

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

    useEffect(() => {
        if (!activeSection || activeSection.subsections.length === 0) {
            setActiveSubsectionId(null);

            return;
        }

        const stillExists = activeSection.subsections.some(
            (subsection) => subsectionKey(subsection) === activeSubsectionId,
        );

        if (!stillExists) {
            setActiveSubsectionId(subsectionKey(activeSection.subsections[0]));
        }
    }, [activeSection, activeSubsectionId]);

    const activeSubsection = useMemo(() => {
        if (!activeSection) {
            return null;
        }

        return (
            activeSection.subsections.find(
                (subsection) =>
                    subsectionKey(subsection) === activeSubsectionId,
            ) ??
            activeSection.subsections[0] ??
            null
        );
    }, [activeSection, activeSubsectionId]);

    const categoryTabs = useMemo(
        () =>
            sections.map((section) => ({
                id: String(section.id),
                label: section.name,
            })),
        [sections],
    );

    const subcategoryTabs = useMemo(() => {
        if (!activeSection || activeSection.subsections.length <= 1) {
            return [];
        }

        return activeSection.subsections.map((subsection) => ({
            id: subsectionKey(subsection),
            label: subsectionLabel(subsection),
        }));
    }, [activeSection]);

    if (sections.length === 0 || activeSection === null || !activeSubsection) {
        return null;
    }

    return (
        <div className="space-y-4">
            <div className="sticky top-[4.25rem] z-20 -mx-4 space-y-2 border-b border-border/60 bg-background/95 px-1 pb-2 backdrop-blur supports-[backdrop-filter]:bg-background/80 md:top-[5.25rem] md:-mx-6 md:px-2">
                <nav aria-label="Categorías del menú">
                    <ScrollableTabs
                        items={categoryTabs}
                        activeId={String(activeSection.id)}
                        onSelect={setActiveSectionId}
                        previousLabel="Ver categorías anteriores"
                        nextLabel="Ver más categorías"
                    />
                </nav>

                {subcategoryTabs.length > 0 ? (
                    <nav aria-label="Subcategorías del menú">
                        <ScrollableTabs
                            items={subcategoryTabs}
                            activeId={subsectionKey(activeSubsection)}
                            onSelect={setActiveSubsectionId}
                            previousLabel="Ver subcategorías anteriores"
                            nextLabel="Ver más subcategorías"
                        />
                    </nav>
                ) : null}
            </div>

            <ProductGrid
                products={activeSubsection.products}
                canOrder={canOrder}
                onAdd={onAdd}
            />
        </div>
    );
}
