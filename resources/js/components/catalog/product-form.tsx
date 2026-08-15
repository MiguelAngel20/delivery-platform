import { Form } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
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

const emptyGroup = (): ProductOptionGroupDraft => ({
    name: '',
    type: 'removable',
    is_required: false,
    min_selection: 0,
    max_selection: 10,
    is_active: true,
    options: [{ name: '', price_modifier: '0', is_default: true, is_available: true }],
});

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
        product?.option_groups?.length
            ? product.option_groups
            : [],
    );

    const categories = useMemo(
        () =>
            options.categories.filter(
                (category) => String(category.branch_id) === branchId,
            ),
        [options.categories, branchId],
    );

    const updateGroup = (index: number, patch: Partial<ProductOptionGroupDraft>) => {
        setGroups((current) =>
            current.map((group, groupIndex) =>
                groupIndex === index ? { ...group, ...patch } : group,
            ),
        );
    };

    const updateOption = (
        groupIndex: number,
        optionIndex: number,
        patch: Partial<ProductOptionDraft>,
    ) => {
        setGroups((current) =>
            current.map((group, gIndex) => {
                if (gIndex !== groupIndex) {
                    return group;
                }

                return {
                    ...group,
                    options: group.options.map((option, oIndex) =>
                        oIndex === optionIndex ? { ...option, ...patch } : option,
                    ),
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
                                onChange={(event) => setBranchId(event.target.value)}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Selecciona sucursal</option>
                                {options.branches.map((branch) => (
                                    <option key={branch.value} value={branch.value}>
                                        {branch.label}
                                    </option>
                                ))}
                            </select>
                            {product?.id ? (
                                <input type="hidden" name="branch_id" value={branchId} />
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
                                defaultValue={product?.product_category_id ?? ''}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Sin categoría</option>
                                {categories.map((category) => (
                                    <option key={category.value} value={category.value}>
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
                                    defaultValue={product?.acquisition_cost ?? ''}
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
                            <Input id="image" name="image" type="file" accept="image/*" />
                        </FormField>

                        <label className="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_available" value="0" />
                            <input
                                name="is_available"
                                type="checkbox"
                                value="1"
                                defaultChecked={product?.is_available ?? true}
                            />
                            Disponible
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input type="hidden" name="is_active" value="0" />
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
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold text-navy">
                                    Personalización
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Solo estas opciones podrán modificar el cliente.
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setGroups((current) => [...current, emptyGroup()])
                                }
                            >
                                + Agregar grupo
                            </Button>
                        </div>

                        {groups.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Sin grupos de opciones.
                            </p>
                        ) : null}

                        {groups.map((group, groupIndex) => (
                            <div
                                key={groupIndex}
                                className="space-y-3 rounded-lg border border-border p-3"
                            >
                                <div className="grid gap-3 md:grid-cols-2">
                                    <FormField label="Grupo" htmlFor={`group-name-${groupIndex}`}>
                                        <Input
                                            id={`group-name-${groupIndex}`}
                                            value={group.name}
                                            onChange={(event) =>
                                                updateGroup(groupIndex, {
                                                    name: event.target.value,
                                                })
                                            }
                                            placeholder="Ej. Ingredientes removibles"
                                        />
                                    </FormField>
                                    <FormField label="Tipo" htmlFor={`group-type-${groupIndex}`}>
                                        <select
                                            id={`group-type-${groupIndex}`}
                                            value={group.type}
                                            onChange={(event) => {
                                                const type = event.target.value;
                                                updateGroup(groupIndex, {
                                                    type,
                                                    is_required: type === 'choice',
                                                    min_selection:
                                                        type === 'choice' ? 1 : 0,
                                                    max_selection:
                                                        type === 'choice' ? 1 : 10,
                                                    options: group.options.map(
                                                        (option) => ({
                                                            ...option,
                                                            is_default:
                                                                type === 'removable',
                                                            price_modifier:
                                                                type === 'addon'
                                                                    ? option.price_modifier
                                                                    : '0',
                                                        }),
                                                    ),
                                                });
                                            }}
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                        >
                                            {options.option_group_types.map((type) => (
                                                <option key={type.value} value={type.value}>
                                                    {type.label}
                                                </option>
                                            ))}
                                        </select>
                                    </FormField>
                                    <FormField label="Mínimo">
                                        <Input
                                            type="number"
                                            min={0}
                                            value={group.min_selection}
                                            onChange={(event) =>
                                                updateGroup(groupIndex, {
                                                    min_selection: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </FormField>
                                    <FormField label="Máximo">
                                        <Input
                                            type="number"
                                            min={0}
                                            value={group.max_selection}
                                            onChange={(event) =>
                                                updateGroup(groupIndex, {
                                                    max_selection: Number(
                                                        event.target.value,
                                                    ),
                                                })
                                            }
                                        />
                                    </FormField>
                                </div>

                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={group.is_required}
                                        onChange={(event) =>
                                            updateGroup(groupIndex, {
                                                is_required: event.target.checked,
                                            })
                                        }
                                    />
                                    Obligatorio
                                </label>

                                <div className="space-y-2">
                                    <p className="text-sm font-medium text-navy">
                                        Opciones
                                    </p>
                                    {group.options.map((option, optionIndex) => (
                                        <div
                                            key={optionIndex}
                                            className="grid gap-2 md:grid-cols-[1fr_120px_auto]"
                                        >
                                            <Input
                                                value={option.name}
                                                placeholder="Nombre"
                                                onChange={(event) =>
                                                    updateOption(groupIndex, optionIndex, {
                                                        name: event.target.value,
                                                    })
                                                }
                                            />
                                            <Input
                                                type="number"
                                                step="0.01"
                                                value={option.price_modifier}
                                                placeholder="+ precio"
                                                onChange={(event) =>
                                                    updateOption(groupIndex, optionIndex, {
                                                        price_modifier:
                                                            event.target.value,
                                                    })
                                                }
                                            />
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={option.is_default}
                                                    onChange={(event) =>
                                                        updateOption(
                                                            groupIndex,
                                                            optionIndex,
                                                            {
                                                                is_default:
                                                                    event.target
                                                                        .checked,
                                                            },
                                                        )
                                                    }
                                                />
                                                Default
                                            </label>
                                        </div>
                                    ))}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            updateGroup(groupIndex, {
                                                options: [
                                                    ...group.options,
                                                    {
                                                        name: '',
                                                        price_modifier: '0',
                                                        is_default:
                                                            group.type === 'removable',
                                                        is_available: true,
                                                    },
                                                ],
                                            })
                                        }
                                    >
                                        + Opción
                                    </Button>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="text-danger"
                                    onClick={() =>
                                        setGroups((current) =>
                                            current.filter(
                                                (_, index) => index !== groupIndex,
                                            ),
                                        )
                                    }
                                >
                                    Quitar grupo
                                </Button>
                            </div>
                        ))}
                        {errors.option_groups ? (
                            <p className="text-sm text-danger">{errors.option_groups}</p>
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
