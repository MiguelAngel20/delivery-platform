export const PASSWORD_MIN_LENGTH = 8;

const HAS_UPPERCASE = /[A-Z]/;
const HAS_NUMBER = /\d/;
const HAS_SPECIAL = /[^A-Za-z0-9]/;

export function validatePasswordStrength(password: string): string | undefined {
    if (password.length < PASSWORD_MIN_LENGTH) {
        return `La contraseña debe tener al menos ${PASSWORD_MIN_LENGTH} caracteres.`;
    }

    if (!HAS_UPPERCASE.test(password)) {
        return 'La contraseña debe incluir al menos una letra mayúscula.';
    }

    if (!HAS_NUMBER.test(password)) {
        return 'La contraseña debe incluir al menos un número.';
    }

    if (!HAS_SPECIAL.test(password)) {
        return 'La contraseña debe incluir al menos un carácter especial (ej. #, $, %).';
    }

    return undefined;
}

export const PASSWORD_REQUIREMENTS_HINT =
    'Mínimo 8 caracteres, con mayúscula, número y carácter especial (#, $, %, etc.).';
