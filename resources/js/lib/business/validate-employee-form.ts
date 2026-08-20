export type EmployeeFormClientErrors = Record<string, string>;

export function resolveFieldError(
    key: string,
    clientErrors: EmployeeFormClientErrors,
    serverErrors: Record<string, string>,
): string | undefined {
    return clientErrors[key] ?? serverErrors[key];
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function validateEmployeeForm(input: {
    firstName: string;
    lastName: string;
    email: string;
    phone: string;
    role: string;
    status: string;
    branchId: number | null;
}): EmployeeFormClientErrors {
    const errors: EmployeeFormClientErrors = {};

    const firstName = input.firstName.trim();

    if (firstName === '') {
        errors.first_name = 'El nombre es obligatorio.';
    } else if (firstName.length > 100) {
        errors.first_name = 'El nombre no puede superar 100 caracteres.';
    }

    const lastName = input.lastName.trim();

    if (lastName === '') {
        errors.last_name = 'Los apellidos son obligatorios.';
    } else if (lastName.length > 100) {
        errors.last_name = 'Los apellidos no pueden superar 100 caracteres.';
    }

    const email = input.email.trim();

    if (email === '') {
        errors.email = 'El correo es obligatorio.';
    } else if (email.length > 255) {
        errors.email = 'El correo no puede superar 255 caracteres.';
    } else if (!EMAIL_PATTERN.test(email)) {
        errors.email = 'Ingresa un correo válido.';
    }

    const phone = input.phone.trim();

    if (phone === '') {
        errors.phone = 'El teléfono es obligatorio.';
    } else if (phone.length > 30) {
        errors.phone = 'El teléfono no puede superar 30 caracteres.';
    }

    if (input.role.trim() === '') {
        errors.role = 'Selecciona un rol.';
    }

    if (input.status.trim() === '') {
        errors.status = 'Selecciona un estado.';
    }

    if (input.branchId === null) {
        errors.branch_ids = 'Selecciona una sucursal asignada.';
    }

    return errors;
}
