/**
 * Digits only from a phone string.
 */
export function phoneDigits(phone: string | null | undefined): string {
    return (phone ?? '').replace(/\D+/g, '');
}

/**
 * Normalize MX-local 10-digit numbers to include country code 52 for WhatsApp.
 */
export function whatsappDigits(phone: string | null | undefined): string {
    const digits = phoneDigits(phone);

    if (digits === '') {
        return '';
    }

    if (digits.length === 10) {
        return `52${digits}`;
    }

    return digits;
}

export function telHref(phone: string | null | undefined): string | null {
    const digits = phoneDigits(phone);

    return digits !== '' ? `tel:${digits}` : null;
}

export function whatsappHref(
    phone: string | null | undefined,
    text?: string,
): string | null {
    const digits = whatsappDigits(phone);

    if (digits === '') {
        return null;
    }

    const base = `https://wa.me/${digits}`;

    if (text && text.trim() !== '') {
        return `${base}?text=${encodeURIComponent(text.trim())}`;
    }

    return base;
}
