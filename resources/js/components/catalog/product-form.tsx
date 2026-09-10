import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import type { CatalogFormOptions } from '@/components/catalog/category-form';
import type { ProductOptionGroupDraft } from '@/components/catalog/product-option-group-types';
import {
    buildGroup,
    emptyOption,
    findGroupIndex,
    SECTION_CONFIG,
} from '@/components/catalog/product-option-groups-config';
import { ProductOptionGroupsFields } from '@/components/catalog/product-option-groups-fields';
import { FormField } from '@/components/forms/form-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import {
    resolveFieldError,
    sanitizeProductOptionGroups,
    sizeGroupMinPrice,
    validateProductForm,
    type ProductFormClientErrors,
} from '@/lib/catalog/validate-product-form';

export type {
    ProductOptionDraft,
    ProductOptionGroupDraft,
} from '@/components/catalog/product-option-group-types';

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
    image_path?: string | null;
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

export function ProductForm({
    options,
    product,
    action,
    submitLabel,
    cancelSlot,
    showAcquisitionCost = false,
}: ProductFormProps) {
    const productImages = options.product_images ?? [];
    const [branchId, setBranchId] = useState(product?.branch_id ?? '');
    const [groups, setGroups] = useState<ProductOptionGroupDraft[]>(
        product?.option_groups?.length ? product.option_groups : [],
    );
    const [listPrice, setListPrice] = useState(product?.list_price ?? '');
    const [clientErrors, setClientErrors] = useState<ProductFormClientErrors>(
        {},
    );
    const [selectedImagePath, setSelectedImagePath] = useState<string>(
        product?.image_path ?? '',
    );
    const [imagePreviewUrl, setImagePreviewUrl] = useState<string | null>(
        product?.image_url ?? null,
    );
    const [hasNewFile, setHasNewFile] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);
    const optionGroupsInputRef = useRef<HTMLInputElement>(null);
    const formRef = useRef<{ getData: () => Record<string, unknown> } | null>(
        null,
    );

    useEffect(() => {
        setClientErrors({});
        setSelectedImagePath(product?.image_path ?? '');
        setImagePreviewUrl(product?.image_url ?? null);
        setHasNewFile(false);
        setListPrice(product?.list_price ?? '');
    }, [product?.id, product?.image_path, product?.image_url, product?.list_price]);

    const sizeGroupIndex = findGroupIndex(groups, 'size');
    const sizeGroup =
        sizeGroupIndex === -1 ? undefined : groups[sizeGroupIndex];
    const sizesEnabled = sizeGroup !== undefined;
    const derivedListPrice = sizeGroupMinPrice(groups);

    useEffect(() => {
        if (derivedListPrice !== null) {
            setListPrice(derivedListPrice);
        }
    }, [derivedListPrice]);

    const onChangeGroups = (next: ProductOptionGroupDraft[]) => {
        setGroups(next);
    };

    function validateBeforeSubmit(): boolean {
        const data = formRef.current?.getData() ?? {};
        const validationErrors = validateProductForm({
            branchId,
            name: String(data.name ?? ''),
            listPrice: sizesEnabled
                ? (derivedListPrice ?? listPrice)
                : listPrice || String(data.list_price ?? ''),
            isEditing: Boolean(product?.id),
            groups,
        });

        if (Object.keys(validationErrors).length > 0) {
            setClientErrors(validationErrors);

            return false;
        }

        setClientErrors({});

        if (optionGroupsInputRef.current) {
            optionGroupsInputRef.current.value = JSON.stringify(
                sanitizeProductOptionGroups(groups),
            );
        }

        return true;
    }

    const toggleSizes = (enabled: boolean) => {
        if (enabled) {
            if (findGroupIndex(groups, 'size') !== -1) {
                return;
            }

            onChangeGroups([...groups, buildGroup('size')]);

            return;
        }

        onChangeGroups(groups.filter((group) => group.type !== 'size'));
    };

    const updateSizeOption = (
        optionIndex: number,
        patch: Partial<ProductOptionGroupDraft['options'][number]>,
    ) => {
        onChangeGroups(
            groups.map((group) => {
                if (group.type !== 'size') {
                    return group;
                }

                return {
                    ...group,
                    options: group.options.map((option, index) =>
                        index === optionIndex ? { ...option, ...patch } : option,
                    ),
                };
            }),
        );
    };

    const addSizeOption = () => {
        onChangeGroups(
            groups.map((group) =>
                group.type === 'size'
                    ? {
                          ...group,
                          options: [...group.options, emptyOption('size')],
                      }
                    : group,
            ),
        );
    };

    const removeSizeOption = (optionIndex: number) => {
        onChangeGroups(
            groups.map((group) => {
                if (group.type !== 'size') {
                    return group;
                }

                const filtered = group.options.filter(
                    (_, index) => index !== optionIndex,
                );

                return {
                    ...group,
                    options:
                        filtered.length === 0
                            ? [emptyOption('size')]
                            : filtered,
                };
            }),
        );
    };

    const categories = useMemo(
        () =>
            options.categories.filter(
                (category) => String(category.branch_id) === branchId,
            ),
        [options.categories, branchId],
    );

    const principalCategories = useMemo(
        () => categories.filter((category) => category.is_root !== false && !category.parent_id),
        [categories],
    );

    const initialPrincipalId = useMemo(() => {
        if (!product?.product_category_id) {
            return '';
        }

        const selected = options.categories.find(
            (category) => category.value === String(product.product_category_id),
        );

        if (!selected) {
            return String(product.product_category_id);
        }

        return selected.parent_id
            ? String(selected.parent_id)
            : selected.value;
    }, [options.categories, product?.product_category_id]);

    const initialSubcategoryId = useMemo(() => {
        if (!product?.product_category_id) {
            return '';
        }

        const selected = options.categories.find(
            (category) => category.value === String(product.product_category_id),
        );

        return selected?.parent_id ? selected.value : '';
    }, [options.categories, product?.product_category_id]);

    const [principalCategoryId, setPrincipalCategoryId] = useState(initialPrincipalId);
    const [subcategoryId, setSubcategoryId] = useState(initialSubcategoryId);

    useEffect(() => {
        setPrincipalCategoryId(initialPrincipalId);
        setSubcategoryId(initialSubcategoryId);
    }, [initialPrincipalId, initialSubcategoryId]);

    const subcategories = useMemo(
        () =>
            categories.filter(
                (category) =>
                    category.parent_id !== null &&
                    category.parent_id !== undefined &&
                    String(category.parent_id) === principalCategoryId,
            ),
        [categories, principalCategoryId],
    );

    const resolvedCategoryId = subcategoryId || principalCategoryId;

    return (
        <Form
            ref={formRef}
            action={action.url}
            method={action.method}
            encType="multipart/form-data"
            className="space-y-6"
            noValidate
            onBefore={() => validateBeforeSubmit()}
        >
            {({ processing, errors }) => (
                <>
                    {Object.keys(clientErrors).length > 0 ? (
                        <div
                            role="alert"
                            className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        >
                            Revisa los campos marcados antes de continuar.
                        </div>
                    ) : null}

                    <input
                        ref={optionGroupsInputRef}
                        type="hidden"
                        name="option_groups"
                        value={JSON.stringify(groups)}
                    />

                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            label="Sucursal"
                            htmlFor="branch_id"
                            required
                            error={resolveFieldError(
                                'branch_id',
                                clientErrors,
                                errors,
                            )}
                        >
                            <select
                                id="branch_id"
                                name="branch_id"
                                disabled={Boolean(product?.id)}
                                value={branchId}
                                onChange={(event) => {
                                    setBranchId(event.target.value);
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.branch_id;

                                        return next;
                                    });
                                }}
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
                            label="Categoría principal"
                            htmlFor="principal_category_id"
                            error={resolveFieldError(
                                'product_category_id',
                                clientErrors,
                                errors,
                            )}
                        >
                            <select
                                id="principal_category_id"
                                value={principalCategoryId}
                                onChange={(event) => {
                                    setPrincipalCategoryId(event.target.value);
                                    setSubcategoryId('');
                                }}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Sin categoría</option>
                                {principalCategories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label.includes(' › ')
                                            ? category.label.split(' › ')[0]
                                            : category.label}
                                    </option>
                                ))}
                            </select>
                        </FormField>

                        <FormField
                            label="Subcategoría (opcional)"
                            htmlFor="subcategory_id"
                        >
                            <select
                                id="subcategory_id"
                                value={subcategoryId}
                                disabled={
                                    principalCategoryId === '' ||
                                    subcategories.length === 0
                                }
                                onChange={(event) =>
                                    setSubcategoryId(event.target.value)
                                }
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-60"
                            >
                                <option value="">
                                    {subcategories.length === 0
                                        ? 'Sin subcategorías'
                                        : 'Ninguna — solo categoría principal'}
                                </option>
                                {subcategories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label.includes(' › ')
                                            ? category.label.split(' › ').pop()
                                            : category.label}
                                    </option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Si el negocio no usa subcategorías, deja este
                                campo vacío.
                            </p>
                        </FormField>

                        <input
                            type="hidden"
                            name="product_category_id"
                            value={resolvedCategoryId}
                        />

                        <FormField
                            label="Nombre"
                            htmlFor="name"
                            required
                            error={resolveFieldError('name', clientErrors, errors)}
                            className="md:col-span-2"
                        >
                            <Input
                                id="name"
                                name="name"
                                maxLength={150}
                                placeholder="Ej. Alitas BBQ, Pizza grande 8 rebanadas"
                                defaultValue={product?.name ?? ''}
                                onChange={() =>
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.name;

                                        return next;
                                    })
                                }
                            />
                        </FormField>

                        <FormField
                            label="Descripción"
                            htmlFor="description"
                            error={resolveFieldError(
                                'description',
                                clientErrors,
                                errors,
                            )}
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
                            error={resolveFieldError(
                                'list_price',
                                clientErrors,
                                errors,
                            )}
                            hint={
                                sizesEnabled
                                    ? 'Se toma del tamaño más económico.'
                                    : undefined
                            }
                        >
                            <Input
                                id="list_price"
                                name="list_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                value={listPrice}
                                readOnly={sizesEnabled}
                                onChange={(event) => {
                                    if (sizesEnabled) {
                                        return;
                                    }

                                    setListPrice(event.target.value);
                                    setClientErrors((current) => {
                                        const next = { ...current };
                                        delete next.list_price;

                                        return next;
                                    });
                                }}
                            />
                        </FormField>

                        {showAcquisitionCost ? (
                            <FormField
                                label="Costo de adquisición (MXN)"
                                htmlFor="acquisition_cost"
                                error={resolveFieldError(
                                    'acquisition_cost',
                                    clientErrors,
                                    errors,
                                )}
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

                        <div className="space-y-3 rounded-xl border border-border bg-surface p-4 md:col-span-2">
                            <label className="flex cursor-pointer items-start gap-3 text-foreground">
                                <Checkbox
                                    checked={sizesEnabled}
                                    onCheckedChange={(checked) =>
                                        toggleSizes(checked === true)
                                    }
                                    className="mt-0.5"
                                />
                                <div>
                                    <span className="text-sm font-medium">
                                        {SECTION_CONFIG.size.label}
                                    </span>
                                    <p className="text-sm text-muted-foreground">
                                        Opcional. Actívalo solo si el producto
                                        tiene presentaciones con precios
                                        distintos.
                                    </p>
                                </div>
                            </label>

                            {sizesEnabled && sizeGroup ? (
                                <div className="space-y-3 border-t border-border pt-3">
                                    {resolveFieldError(
                                        `option_groups.${sizeGroupIndex}.options`,
                                        clientErrors,
                                        errors,
                                    ) ? (
                                        <p className="text-sm text-destructive">
                                            {resolveFieldError(
                                                `option_groups.${sizeGroupIndex}.options`,
                                                clientErrors,
                                                errors,
                                            )}
                                        </p>
                                    ) : null}

                                    {sizeGroup.options.map(
                                        (option, optionIndex) => (
                                            <div
                                                key={`size-${optionIndex}`}
                                                className="grid gap-3 sm:grid-cols-[1fr_8rem_auto]"
                                            >
                                                <FormField
                                                    label={
                                                        optionIndex === 0
                                                            ? 'Nombre'
                                                            : undefined
                                                    }
                                                    htmlFor={`size-name-${optionIndex}`}
                                                    error={resolveFieldError(
                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.name`,
                                                        clientErrors,
                                                        errors,
                                                    )}
                                                >
                                                    <Input
                                                        id={`size-name-${optionIndex}`}
                                                        placeholder={
                                                            SECTION_CONFIG.size
                                                                .optionPlaceholder
                                                        }
                                                        value={option.name}
                                                        onChange={(event) => {
                                                            updateSizeOption(
                                                                optionIndex,
                                                                {
                                                                    name: event
                                                                        .target
                                                                        .value,
                                                                },
                                                            );
                                                            setClientErrors(
                                                                (current) => {
                                                                    const next =
                                                                        {
                                                                            ...current,
                                                                        };
                                                                    delete next[
                                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.name`
                                                                    ];

                                                                    return next;
                                                                },
                                                            );
                                                        }}
                                                    />
                                                </FormField>
                                                <FormField
                                                    label={
                                                        optionIndex === 0
                                                            ? 'Precio (MXN)'
                                                            : undefined
                                                    }
                                                    htmlFor={`size-price-${optionIndex}`}
                                                    error={resolveFieldError(
                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.price_modifier`,
                                                        clientErrors,
                                                        errors,
                                                    )}
                                                >
                                                    <Input
                                                        id={`size-price-${optionIndex}`}
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        placeholder="0.00"
                                                        value={
                                                            option.price_modifier
                                                        }
                                                        onChange={(event) => {
                                                            updateSizeOption(
                                                                optionIndex,
                                                                {
                                                                    price_modifier:
                                                                        event
                                                                            .target
                                                                            .value,
                                                                },
                                                            );
                                                            setClientErrors(
                                                                (current) => {
                                                                    const next =
                                                                        {
                                                                            ...current,
                                                                        };
                                                                    delete next[
                                                                        `option_groups.${sizeGroupIndex}.options.${optionIndex}.price_modifier`
                                                                    ];

                                                                    return next;
                                                                },
                                                            );
                                                        }}
                                                    />
                                                </FormField>
                                                <div
                                                    className={
                                                        optionIndex === 0
                                                            ? 'flex items-end pb-0.5'
                                                            : 'flex items-center'
                                                    }
                                                >
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="text-muted-foreground hover:text-destructive"
                                                        aria-label="Quitar tamaño"
                                                        onClick={() =>
                                                            removeSizeOption(
                                                                optionIndex,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        ),
                                    )}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addSizeOption}
                                    >
                                        Agregar tamaño
                                    </Button>
                                </div>
                            ) : null}
                        </div>

                        <FormField
                            label="Imagen"
                            htmlFor="image"
                            error={
                                resolveFieldError(
                                    'image',
                                    clientErrors,
                                    errors,
                                ) ??
                                resolveFieldError(
                                    'existing_image_path',
                                    clientErrors,
                                    errors,
                                )
                            }
                            hint="Puedes reutilizar una imagen del catálogo o subir una nueva."
                        >
                            <div className="space-y-3">
                                {imagePreviewUrl ? (
                                    <div className="overflow-hidden rounded-lg border border-border bg-secondary">
                                        <img
                                            src={imagePreviewUrl}
                                            alt="Vista previa del producto"
                                            className="h-40 w-full object-cover"
                                        />
                                    </div>
                                ) : null}

                                {productImages.length > 0 ? (
                                    <div className="space-y-2">
                                        <p className="text-xs font-medium text-muted-foreground">
                                            Imágenes del negocio
                                        </p>
                                        <div className="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                                            {productImages.map((image) => {
                                                const selected =
                                                    !hasNewFile &&
                                                    selectedImagePath ===
                                                        image.path;

                                                return (
                                                    <button
                                                        key={image.path}
                                                        type="button"
                                                        onClick={() => {
                                                            if (
                                                                fileInputRef.current
                                                            ) {
                                                                fileInputRef.current.value =
                                                                    '';
                                                            }
                                                            setHasNewFile(
                                                                false,
                                                            );
                                                            setSelectedImagePath(
                                                                image.path,
                                                            );
                                                            setImagePreviewUrl(
                                                                image.url,
                                                            );
                                                        }}
                                                        className={
                                                            selected
                                                                ? 'overflow-hidden rounded-md border-2 border-primary ring-2 ring-primary/20'
                                                                : 'overflow-hidden rounded-md border border-border hover:border-primary/50'
                                                        }
                                                        aria-pressed={selected}
                                                        aria-label="Usar esta imagen"
                                                    >
                                                        <img
                                                            src={image.url}
                                                            alt=""
                                                            className="aspect-square w-full object-cover"
                                                        />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ) : null}

                                <input
                                    type="hidden"
                                    name="existing_image_path"
                                    value={selectedImagePath}
                                />

                                <Input
                                    ref={fileInputRef}
                                    id="image"
                                    name="image"
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => {
                                        const file =
                                            event.target.files?.[0] ?? null;

                                        if (!file) {
                                            return;
                                        }

                                        setHasNewFile(true);
                                        setSelectedImagePath('');
                                        setImagePreviewUrl(
                                            URL.createObjectURL(file),
                                        );
                                    }}
                                />
                            </div>
                        </FormField>

                        <label className="flex items-center gap-2 text-sm text-foreground">
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
                        <label className="flex items-center gap-2 text-sm text-foreground">
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
                        <label className="flex items-center gap-2 text-sm text-foreground md:col-span-2">
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

                    <ProductOptionGroupsFields
                        groups={groups}
                        onChange={setGroups}
                        clientErrors={clientErrors}
                        serverErrors={errors}
                        onClearError={(key) =>
                            setClientErrors((current) => {
                                const next = { ...current };
                                delete next[key];

                                return next;
                            })
                        }
                    />

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
