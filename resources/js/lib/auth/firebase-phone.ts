export const PHONE_RESEND_COOLDOWN_SECONDS = 60;

export function canSendPhoneCode(input: {
    busy: boolean;
    cooldownSeconds: number;
}): boolean {
    return !input.busy && input.cooldownSeconds <= 0;
}

export function phoneResendLabel(cooldownSeconds: number): string {
    if (cooldownSeconds > 0) {
        return `Reenviar código en ${cooldownSeconds} s`;
    }

    return 'Reenviar código';
}

export function tickCooldown(cooldownSeconds: number): number {
    return cooldownSeconds > 0 ? cooldownSeconds - 1 : 0;
}

export function firebasePhoneErrorMessage(error: unknown): string {
    const code =
        typeof error === 'object' &&
        error !== null &&
        'code' in error &&
        typeof error.code === 'string'
            ? error.code
            : '';

    switch (code) {
        case 'auth/invalid-phone-number':
        case 'auth/missing-phone-number':
            return 'El número de teléfono no es válido.';
        case 'auth/invalid-verification-code':
            return 'El código ingresado no es correcto.';
        case 'auth/code-expired':
            return 'El código ha expirado. Solicita uno nuevo.';
        case 'auth/too-many-requests':
        case 'auth/quota-exceeded':
            return 'Se realizaron demasiados intentos. Espera un momento antes de volver a intentarlo.';
        case 'auth/captcha-check-failed':
        case 'auth/invalid-app-credential':
            return 'No se pudo comprobar que eres una persona. Inténtalo de nuevo.';
        case 'auth/network-request-failed':
            return 'No hay conexión para enviar el SMS. Revisa tu internet e inténtalo de nuevo.';
        default:
            return 'No se pudo completar la verificación. Inténtalo de nuevo.';
    }
}
