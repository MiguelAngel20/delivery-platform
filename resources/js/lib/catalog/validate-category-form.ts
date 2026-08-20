export type CategoryFormClientErrors = Record<string, string>;

export function resolveFieldError(
    key: string,
    clientErrors: CategoryFormClientErrors,
    serverErrors: Record<string, string>,
): string | undefined {
    return clientErrors[key] ?? serverErrors[key];
}

export function validateCategoryForm(input: {
    branchId: string;
    name: string;
    requiresBranch: boolean;
}): CategoryFormClientErrors {
    const errors: CategoryFormClientErrors = {};

    if (input.requiresBranch && input.branchId.trim() === '') {
        errors.branch_id = 'Selecciona una sucursal.';
    }

    const name = input.name.trim();

    if (name === '') {
        errors.name = 'El nombre de la categoría es obligatorio.';
    } else if (name.length > 100) {
        errors.name = 'El nombre no puede superar 100 caracteres.';
    }

    return errors;
}
