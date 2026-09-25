import type { PromotionItemDraft } from '@/components/catalog/promotion-form';
import {
    sanitizeProductOptionGroups,
    validateProductOptionGroups,
} from '@/lib/catalog/validate-product-form';

export type PromotionFormClientErrors = Record<string, string>;

export function resolveFieldError(
    key: string,
    clientErrors: PromotionFormClientErrors,
    serverErrors: Record<string, string>,
): string | undefined {
    return clientErrors[key] ?? serverErrors[key];
}

function parseDateTime(value: string): Date | null {
    if (value.trim() === '') {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
}

export function validatePromotionItemAtIndex(
    item: PromotionItemDraft,
    index: number,
): PromotionFormClientErrors {
    const errors: PromotionFormClientErrors = {};

    if (item.is_external_item) {
        if (item.name.trim() === '') {
            errors[`items.${index}.name`] = 'El ítem externo requiere nombre.';
        } else if (item.name.trim().length > 150) {
            errors[`items.${index}.name`] =
                'El nombre del ítem no puede superar 150 caracteres.';
        }

        if (String(item.product_id ?? '').trim() !== '') {
            errors[`items.${index}.product_id`] =
                'Un ítem externo no debe tener producto del menú.';
        }

        const groups = item.option_groups ?? [];

        if (groups.length > 0) {
            Object.assign(
                errors,
                validateProductOptionGroups(
                    groups,
                    `items.${index}.option_groups`,
                ),
            );
        }
    } else if (String(item.product_id ?? '').trim() === '') {
        errors[`items.${index}.product_id`] =
            'Debes seleccionar un producto del menú.';
    }

    const quantity = item.quantity.trim();

    if (quantity === '') {
        errors[`items.${index}.quantity`] =
            'La cantidad del ítem es obligatoria.';
    } else {
        const parsedQuantity = Number(quantity);

        if (Number.isNaN(parsedQuantity) || parsedQuantity <= 0) {
            errors[`items.${index}.quantity`] =
                'La cantidad debe ser mayor a 0.';
        }
    }

    return errors;
}

export function validatePromotionItemDraft(
    item: PromotionItemDraft,
): Record<string, string> {
    const prefixed = validatePromotionItemAtIndex(item, 0);
    const errors: Record<string, string> = {};

    Object.entries(prefixed).forEach(([key, value]) => {
        if (key.startsWith('items.0.')) {
            errors[key.slice('items.0.'.length)] = value;
        }
    });

    return errors;
}

export function validatePromotionForm(input: {
    branchId: string;
    name: string;
    promotionPrice: string;
    status: string;
    startsAt: string;
    endsAt: string;
    isRecurring?: boolean;
    recurrenceStartsOn?: string;
    recurrenceEndsOn?: string;
    recurringHoursRaw?: string;
    isEditing: boolean;
    items: PromotionItemDraft[];
}): PromotionFormClientErrors {
    const errors: PromotionFormClientErrors = {};

    if (!input.isEditing && input.branchId.trim() === '') {
        errors.branch_id = 'Selecciona una sucursal.';
    }

    if (input.status.trim() === '') {
        errors.status = 'Selecciona un estado.';
    }

    const name = input.name.trim();

    if (name === '') {
        errors.name = 'El nombre de la promoción es obligatorio.';
    } else if (name.length > 150) {
        errors.name = 'El nombre no puede superar 150 caracteres.';
    }

    const promotionPrice = input.promotionPrice.trim();

    if (promotionPrice === '') {
        errors.promotion_price = 'El precio promocional es obligatorio.';
    } else {
        const parsed = Number(promotionPrice);

        if (Number.isNaN(parsed) || parsed < 0) {
            errors.promotion_price =
                'Ingresa un precio promocional válido mayor o igual a 0.';
        }
    }

    if (input.isRecurring) {
        const startsOn = input.recurrenceStartsOn?.trim() ?? '';
        const endsOn = input.recurrenceEndsOn?.trim() ?? '';

        if (startsOn === '') {
            errors.recurrence_starts_on =
                'Indica desde cuándo inicia la recurrencia.';
        }

        if (startsOn !== '' && endsOn !== '' && endsOn < startsOn) {
            errors.recurrence_ends_on =
                'La fecha de fin de recurrencia debe ser posterior o igual al inicio.';
        }

        let hours: Array<{
            day?: string;
            is_open?: boolean;
            opens_at?: string | null;
            closes_at?: string | null;
        }> = [];

        try {
            const parsed = JSON.parse(input.recurringHoursRaw ?? '[]');
            hours = Array.isArray(parsed) ? parsed : [];
        } catch {
            errors.recurring_hours = 'Los horarios recurrentes no son válidos.';
        }

        const openDays = hours.filter((row) => Boolean(row.is_open));

        if (!errors.recurring_hours && openDays.length === 0) {
            errors.recurring_hours =
                'Selecciona al menos un día con horario para la promoción recurrente.';
        }

        hours.forEach((row, index) => {
            if (!row.is_open) {
                return;
            }

            if (!row.opens_at) {
                errors[`recurring_hours.${index}.opens_at`] =
                    'Indica la hora de inicio.';
            }

            if (!row.closes_at) {
                errors[`recurring_hours.${index}.closes_at`] =
                    'Indica la hora de fin.';
            }

            if (
                row.opens_at &&
                row.closes_at &&
                String(row.closes_at) <= String(row.opens_at)
            ) {
                errors[`recurring_hours.${index}.closes_at`] =
                    'La hora de fin debe ser posterior a la de inicio.';
            }
        });
    } else {
        const startsAt = parseDateTime(input.startsAt);
        const endsAt = parseDateTime(input.endsAt);

        if (input.startsAt.trim() !== '' && startsAt === null) {
            errors.starts_at = 'La fecha de inicio no es válida.';
        }

        if (input.endsAt.trim() !== '' && endsAt === null) {
            errors.ends_at = 'La fecha de fin no es válida.';
        }

        if (startsAt !== null && endsAt !== null && endsAt < startsAt) {
            errors.ends_at =
                'La fecha de fin debe ser posterior o igual al inicio.';
        }
    }

    if (input.items.length === 0) {
        return errors;
    }

    input.items.forEach((item, index) => {
        Object.assign(errors, validatePromotionItemAtIndex(item, index));
    });

    return errors;
}

export function serializePromotionItems(items: PromotionItemDraft[]) {
    return items.map((item) => {
        if (item.is_external_item) {
            const groups = item.option_groups ?? [];
            const sanitizedGroups =
                groups.length > 0
                    ? sanitizeProductOptionGroups(groups)
                    : undefined;

            return {
                ...item,
                option_groups:
                    sanitizedGroups && sanitizedGroups.length > 0
                        ? sanitizedGroups
                        : undefined,
            };
        }

        const { option_groups: _optionGroups, ...menuItem } = item;

        return menuItem;
    });
}
