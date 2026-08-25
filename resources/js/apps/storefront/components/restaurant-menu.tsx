import { useEffect, useMemo, useState } from 'react';
import { ProductCard } from '@/apps/storefront/components/product-card';
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
                    <div className="grid gap-2 md:gap-3">
                        {subsection.products.map((product) => (
                            <ProductCard
                                key={product.id}
                                product={{
                                    id: String(product.id),
                                    name: product.name,
                                    description: product.description,
                                    price: product.price,
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
            <nav aria-label="Categorías del menú" className="-mx-1 md:hidden">
                <div className="flex gap-2 overflow-x-auto px-1 pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    {sections.map((section) => {
                        const id = String(section.id);
                        const selected = String(activeSection.id) === id;

                        return (
                            <button
                                key={id}
                                type="button"
                                onClick={() => setActiveSectionId(id)}
                                aria-pressed={selected}
                                className={cn(
                                    'shrink-0 rounded-full border px-3 py-1.5 text-xs font-medium whitespace-nowrap transition-colors',
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
            </nav>

            {/* Mobile: only the selected category */}
            <div className="md:hidden">
                <SectionBlock
                    section={activeSection}
                    canOrder={canOrder}
                    onAdd={onAdd}
                />
            </div>

            {/* Desktop: full menu */}
            <div className="hidden space-y-8 md:block">
                {sections.map((section) => (
                    <SectionBlock
                        key={String(section.id)}
                        section={section}
                        canOrder={canOrder}
                        onAdd={onAdd}
                    />
                ))}
            </div>
        </div>
    );
}
