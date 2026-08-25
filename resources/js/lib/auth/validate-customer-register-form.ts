import { validatePasswordStrength } from '@/lib/auth/password-requirements';

export type CustomerRegisterClientErrors = Record<string, string>;

export type CustomerRegisterDialCode = {
    dial: string;
    label: string;
    national_length: number;
};

export type CustomerRegisterFormInput = {
    first_name: string;
    last_name: string;
    email: string;
    phone_dial_code: string;
    phone_national: string;
    password: string;
    password_confirmation: string;
    address_label: string;
    address_text: string;
    latitude: string;
    longitude: string;
};

export function resolveFieldError(
    key: string,
    clientErrors: CustomerRegisterClientErrors,
    serverErrors: Record<string, string>,
): string | undefined {
    return clientErrors[key] ?? serverErrors[key];
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function validateCustomerRegisterForm(
    input: CustomerRegisterFormInput,
    dialCodes: CustomerRegisterDialCode[],
): CustomerRegisterClientErrors {
    const errors: CustomerRegisterClientErrors = {};

    const firstName = input.first_name.trim();

    if (firstName === '') {
        errors.first_name = 'Indica tu nombre.';
    } else if (firstName.length > 100) {
        errors.first_name = 'El nombre no puede superar 100 caracteres.';
    }

    const lastName = input.last_name.trim();

    if (lastName === '') {
        errors.last_name = 'Indica tus apellidos.';
    } else if (lastName.length > 100) {
        errors.last_name = 'Los apellidos no pueden superar 100 caracteres.';
    }

    const email = input.email.trim();

    if (email === '') {
        errors.email = 'Indica tu correo electrónico.';
    } else if (email.length > 255) {
        errors.email = 'El correo electrónico no puede superar 255 caracteres.';
    } else if (!EMAIL_PATTERN.test(email)) {
        errors.email = 'El correo electrónico no es válido.';
    }

    const dial = dialCodes.find((item) => item.dial === input.phone_dial_code);

    if (!dial) {
        errors.phone_dial_code = 'Selecciona el código de país.';
    }

    const phoneNational = input.phone_national.replace(/\D+/g, '');

    if (phoneNational === '') {
        errors.phone_national = 'Indica tu número de teléfono.';
    } else if (dial && phoneNational.length !== dial.national_length) {
        errors.phone_national = `El número debe tener ${dial.national_length} dígitos para ese país.`;
    }

    const password = input.password;

    if (password === '') {
        errors.password = 'Elige una contraseña para entrar después.';
    } else {
        const passwordError = validatePasswordStrength(password);

        if (passwordError) {
            errors.password = passwordError;
        }
    }

    if (input.password_confirmation === '') {
        errors.password_confirmation = 'Confirma tu contraseña.';
    } else if (password !== input.password_confirmation) {
        errors.password_confirmation =
            'La confirmación de contraseña no coincide.';
        errors.password = 'La confirmación de contraseña no coincide.';
    }

    const addressLabel = input.address_label.trim();

    if (addressLabel.length > 100) {
        errors.address_label = 'La etiqueta no puede superar 100 caracteres.';
    }

    const latitude = Number(input.latitude);
    const longitude = Number(input.longitude);
    const hasLocation =
        input.address_text.trim() !== '' &&
        input.latitude !== '' &&
        input.longitude !== '' &&
        Number.isFinite(latitude) &&
        Number.isFinite(longitude) &&
        latitude >= -90 &&
        latitude <= 90 &&
        longitude >= -180 &&
        longitude <= 180;

    if (!hasLocation) {
        errors.address_text =
            'Selecciona tu dirección de entrega en el mapa.';
    } else if (input.address_text.trim().length > 255) {
        errors.address_text = 'La dirección no puede superar 255 caracteres.';
    }

    return errors;
}

export function validateCustomerEmailCode(code: string): string | undefined {
    const digits = code.replace(/\D+/g, '');

    if (digits === '') {
        return 'Ingresa el código de 6 dígitos.';
    }

    if (digits.length !== 6) {
        return 'El código debe tener 6 dígitos.';
    }

    return undefined;
}
