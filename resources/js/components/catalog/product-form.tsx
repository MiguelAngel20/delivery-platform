import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type ProductOptionDraft = {
    name: string;
    description?: string;
    price_modifier: string;
    is_default: boolean;
    is_available: boolean;
};

export type ProductOptionGroupDraft = {
    name: string;
    type: string;
    is_required: boolean;
    min_selection: number;
    max_selection: number;
    is_active: boolean;
    options: ProductOptionDraft[];
};

export type ProductFormValues = {
    id?: number;
    branch_id: string;
    product_category_id?: string | null;
    name: string;
    description?: string | null;
    list_price: string;
    acquisition_cost?: string;
    is_available?: boolean;
    is_active?: boolean;
    allow_special_instructions?: boolean;
    image_url?: string | null;
    option_groups?: ProductOptionGroupDraft[];
};

type ProductFormProps = {
    options: CatalogFormOptions;
    product?: ProductFormValues;
    action: { url: string; method: 'post' };
    submitLabel: string;
    cancelSlot?: ReactNode;
    showAcquisitionCost?: boolean;
};

type SectionType = 'choice' | 'addon' | 'removable';

const SECTION_CONFIG: Record<
    SectionType,
    {
        label: string;
        description: string;
        showPrice: boolean;
        showLimits: boolean;
        defaultMin: number;
        defaultMax: number;
        isRequired: boolean;
        optionPlaceholder: string;
    }
> = {
    choice: {
        label: 'Variantes',
        description:
            'Sabores, salsas, ingredientes o especialidades que el cliente puede elegir.',
        showPrice: false,
        showLimits: true,
        defaultMin: 1,
        defaultMax: 1,
        isRequired: true,
        optionPlaceholder: 'Ej. BBQ, Hawaiana, Pepperoni',
    },
    addon: {
        label: 'Extras',
        description:
            'Complementos que el cliente puede agregar, con o sin costo adicional.',
        showPrice: true,
        showLimits: true,
        defaultMin: 0,
        defaultMax: 5,
        isRequired: false,
        optionPlaceholder: 'Ej. Extra queso, Aderezo ranch',
    },
    removable: {
        label: 'Quitar ingredientes',
        description:
            'Ingredientes que el cliente puede pedir que se retiren del producto.',
        showPrice: false,
        showLimits: false,
        defaultMin: 0,
        defaultMax: 99,
        isRequired: false,
        optionPlaceholder: 'Ej. Sin cebolla, Sin jalapeño',
    },
};

const SECTION_ORDER: SectionType[] = ['choice', 'addon', 'removable'];

function emptyOption(type: SectionType): ProductOptionDraft {
    return {
        name: '',
        price_modifier: '0',
        is_default: type === 'removable',
        is_available: true,
    };
}

function buildGroup(type: SectionType): ProductOptionGroupDraft {
    const config = SECTION_CONFIG[type];

    return {
        name: config.label,
        type,
        is_required: config.isRequired,
        min_selection: config.defaultMin,
        max_selection: config.defaultMax,
        is_active: true,
        options: [emptyOption(type)],
    };
}

function findGroupIndex(
    groups: ProductOptionGroupDraft[],
    type: SectionType,
): number {
    return groups.findIndex((g) => g.type === type);
}

type NumericLimitInputProps = {
    id: string;
    value: number;
    minAllowed: number;
    onCommit: (value: number) => void;
};

function NumericLimitInput({
    id,
    value,
    minAllowed,
    onCommit,
}: NumericLimitInputProps) {
    const [draft, setDraft] = useState(String(value));

    useEffect(() => {
        setDraft(String(value));
    }, [value]);

    const commit = () => {
        const parsed = draft === '' ? minAllowed : parseInt(draft, 10);
        const normalized = Number.isNaN(parsed)
            ? minAllowed
            : Math.max(minAllowed, parsed);

        onCommit(normalized);
        setDraft(String(normalized));
    };

    return (
        <Input
            id={id}
            type="text"
            inputMode="numeric"
            value={draft}
            onChange={(event) => {
                const next = event.target.value;

                if (next === '' || /^\d+$/.test(next)) {
                    setDraft(next);
                }
            }}
            onBlur={commit}
        />
    );
}

export function ProductForm({
    options,
    product,
    action,
    submitLabel,
    cancelSlot,
    showAcquisitionCost = false,
}: ProductFormProps) {
    const [branchId, setBranchId] = useState(product?.branch_id ?? '');
    const [groups, setGroups] = useState<ProductOptionGroupDraft[]>(
        product?.option_groups?.length ? product.option_groups : [],
    );

    const categories = useMemo(
        () =>
            options.categories.filter(
                (category) => String(category.branch_id) === branchId,
            ),
        [options.categories, branchId],
    );

    const toggleSection = (type: SectionType, enabled: boolean) => {
        setGroups((current) => {
            if (enabled) {
                if (findGroupIndex(current, type) !== -1) {
                    return current;
                }

                return [...current, buildGroup(type)];
            }

            return current.filter((g) => g.type !== type);
        });
    };

    const updateGroupByType = (
        type: SectionType,
        patch: Partial<ProductOptionGroupDraft>,
    ) => {
        setGroups((current) =>
            current.map((group) =>
                group.type === type ? { ...group, ...patch } : group,
            ),
        );
    };

    const updateOption = (
        type: SectionType,
        optionIndex: number,
        patch: Partial<ProductOptionDraft>,
    ) => {
        setGroups((current) =>
            current.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                return {
                    ...group,
                    options: group.options.map((option, oIndex) =>
                        oIndex === optionIndex
                            ? { ...option, ...patch }
                            : option,
                    ),
                };
            }),
        );
    };

    const addOption = (type: SectionType) => {
        setGroups((current) =>
            current.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                return {
                    ...group,
                    options: [
                        ...group.options,
                        emptyOption(type as SectionType),
                    ],
                };
            }),
        );
    };

    const removeOption = (type: SectionType, optionIndex: number) => {
        setGroups((current) =>
            current.map((group) => {
                if (group.type !== type) {
                    return group;
                }

                const filtered = group.options.filter(
                    (_, i) => i !== optionIndex,
                );

                return {
                    ...group,
                    options:
                        filtered.length === 0
                            ? [emptyOption(type as SectionType)]
                            : filtered,
                };
            }),
        );
    };

    return (
        <Form
            action={action.url}
            method={action.method}
            encType="multipart/form-data"
            className="space-y-6"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="option_groups"
                        value={JSON.stringify(groups)}
                    />

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Sucursal"
                            htmlFor="branch_id"
                            required
                            error={errors.branch_id}
                        >
                            <select
                                id="branch_id"
                                name="branch_id"
                                required
                                disabled={Boolean(product?.id)}
                                value={branchId}
                                onChange={(event) =>
                                    setBranchId(event.target.value)
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selecciona sucursal</option>
                                {options.branches.map((branch) => (
                                    <option
                                        key={branch.value}
                                        value={branch.value}
                                    >
                                        {branch.label}
                                    </option>
                                ))}
                            </select>
                            {product?.id ? (
                                <input
                                    type="hidden"
                                    name="branch_id"
                                    value={branchId}
                                />
                            ) : null}
                        </FormField>

                        <FormField
                            label="Categoría"
                            htmlFor="product_category_id"
                            error={errors.product_category_id}
                        >
                            <select
                                id="product_category_id"
                                name="product_category_id"
                                defaultValue={
                                    product?.product_category_id ?? ''
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Sin categoría</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Nombre"
                            htmlFor="name"
                            required
                            error={errors.name}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                required
                                maxLength={150}
                                placeholder="Ej. Alitas BBQ, Pizza grande 8 rebanadas"
                                defaultValue={product?.name ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Descripción"
                            htmlFor="description"
                            error={errors.description}
                            className="md:col-span-2"
                        >
                            <Textarea
                                id="description"
                                name="description"
                                rows={3}
                                placeholder="Breve descripción del producto (opcional)"
                                defaultValue={product?.description ?? ''}
                            />
                        </FormField>

                        <FormField
                            label="Precio de lista (MXN)"
                            htmlFor="list_price"
                            required
                            error={errors.list_price}
                        >
                            <Input
                                id="list_price"
                                name="list_price"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                placeholder="0.00"
                                defaultValue={product?.list_price ?? ''}
                            />
                        </FormField>

                        {showAcquisitionCost ? (
                            <FormField
                                label="Costo de adquisición (MXN)"
                                htmlFor="acquisition_cost"
                                error={errors.acquisition_cost}
                                hint="Lo que cobra el establecimiento. El cliente ve el precio de lista."
                            >
                                <Input
                                    id="acquisition_cost"
                                    name="acquisition_cost"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    defaultValue={
                                        product?.acquisition_cost ?? ''
                                    }
                                />
                            </FormField>
                        ) : null}

                        <FormField
                            label="Imagen"
                            htmlFor="image"
                            error={errors.image}
                            hint={
                                product?.image_url
                                    ? 'Sube una nueva imagen para reemplazar la actual.'
                                    : undefined
                            }
                        >
                            <Input
                                id="image"
                                name="image"
                                type="file"
                                accept="image/*"
                            />
                        </FormField>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="hidden"
                                name="is_available"
                                value="0"
                            />
                            <input
                                name="is_available"
                                type="checkbox"
                                value="1"
                                defaultChecked={product?.is_available ?? true}
                            />
                            Disponible
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="hidden"
                                name="is_active"
                                value="0"
                            />
                            <input
                                name="is_active"
                                type="checkbox"
                                value="1"
                                defaultChecked={product?.is_active ?? true}
                            />
                            Activo en catálogo
                        </label>
                        <label className="flex items-center gap-2 text-sm md:col-span-2">
                            <input
                                type="hidden"
                                name="allow_special_instructions"
                                value="0"
                            />
                            <input
                                name="allow_special_instructions"
                                type="checkbox"
                                value="1"
                                defaultChecked={
                                    product?.allow_special_instructions ?? true
                                }
                            />
                            Permitir instrucciones especiales
                        </label>
                    </div>

                    <section className="space-y-4 rounded-xl border border-border bg-white p-4">
                        <div>
                            <h2 className="text-base font-semibold text-navy">
                                Personalización
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Marca las opciones que aplican a este producto.
                                Si no marcas ninguna, el producto se vende tal
                                cual.
                            </p>
                        </div>

                        {SECTION_ORDER.map((type) => {
                            const config = SECTION_CONFIG[type];
                            const group = groups.find(
                                (g) => g.type === type,
                            );
                            const isEnabled = group !== undefined;

                            return (
                                <div
                                    key={type}
                                    className="rounded-lg border border-border"
                                >
                                    <label className="flex cursor-pointer items-start gap-3 p-4">
                                        <Checkbox
                                            checked={isEnabled}
                                            onCheckedChange={(checked) =>
                                                toggleSection(
                                                    type,
                                                    checked === true,
                                                )
                                            }
                                            className="mt-0.5"
                                        />
                                        <div>
                                            <span className="text-sm font-medium text-navy">
                                                {config.label}
                                            </span>
                                            <p className="text-sm text-muted-foreground">
                                                {config.description}
                                            </p>
                                        </div>
                                    </label>

                                    {isEnabled && group ? (
                                        <div className="space-y-3 border-t border-border px-4 pb-4 pt-3">
                                            {config.showLimits ? (
                                                <div className="grid gap-3 sm:grid-cols-2">
                                                    <FormField
                                                        label="Mínimo que debe elegir"
                                                        htmlFor={`${type}-min`}
                                                    >
                                                        <NumericLimitInput
                                                            id={`${type}-min`}
                                                            value={
                                                                group.min_selection
                                                            }
                                                            minAllowed={0}
                                                            onCommit={(min) =>
                                                                updateGroupByType(
                                                                    type,
                                                                    {
                                                                        min_selection:
                                                                            min,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                    <FormField
                                                        label="Máximo que puede elegir"
                                                        htmlFor={`${type}-max`}
                                                    >
                                                        <NumericLimitInput
                                                            id={`${type}-max`}
                                                            value={
                                                                group.max_selection
                                                            }
                                                            minAllowed={1}
                                                            onCommit={(max) =>
                                                                updateGroupByType(
                                                                    type,
                                                                    {
                                                                        max_selection:
                                                                            max,
                                                                    },
                                                                )
                                                            }
                                                        />
                                                    </FormField>
                                                </div>
                                            ) : null}

                                            <div className="space-y-2">
                                                <p className="text-sm font-medium text-navy">
                                                    Opciones
                                                </p>
                                                {group.options.map(
                                                    (option, optionIndex) => (
                                                        <div
                                                            key={optionIndex}
                                                            className={`grid items-center gap-2 ${config.showPrice ? 'grid-cols-[1fr_100px_auto]' : 'grid-cols-[1fr_auto]'}`}
                                                        >
                                                            <Input
                                                                value={
                                                                    option.name
                                                                }
                                                                placeholder={
                                                                    config.optionPlaceholder
                                                                }
                                                                onChange={(e) =>
                                                                    updateOption(
                                                                        type,
                                                                        optionIndex,
                                                                        {
                                                                            name: e
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                            />
                                                            {config.showPrice ? (
                                                                <Input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    value={
                                                                        option.price_modifier
                                                                    }
                                                                    placeholder="+ $0.00"
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        updateOption(
                                                                            type,
                                                                            optionIndex,
                                                                            {
                                                                                price_modifier:
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                />
                                                            ) : null}
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="size-9 text-muted-foreground hover:text-destructive"
                                                                onClick={() =>
                                                                    removeOption(
                                                                        type,
                                                                        optionIndex,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="size-4" />
                                                            </Button>
                                                        </div>
                                                    ),
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        addOption(type)
                                                    }
                                                >
                                                    + Agregar opción
                                                </Button>
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            );
                        })}
                        {errors.option_groups ? (
                            <p className="text-sm text-destructive">
                                {errors.option_groups}
                            </p>
                        ) : null}
                    </section>

                    <div className="flex flex-wrap gap-3">
                        <Button type="submit" disabled={processing}>
                            {submitLabel}
                        </Button>
                        {cancelSlot}
                    </div>
                </>
            )}
        </Form>
    );
}
