export function RecaptchaNotice() {
    return (
        <p className="text-center text-xs leading-relaxed text-muted-foreground">
            Esta verificación está protegida por reCAPTCHA. Aplican la{' '}
            <a
                href="https://policies.google.com/privacy"
                target="_blank"
                rel="noreferrer"
                className="underline underline-offset-2"
            >
                Política de privacidad
            </a>{' '}
            y los{' '}
            <a
                href="https://policies.google.com/terms"
                target="_blank"
                rel="noreferrer"
                className="underline underline-offset-2"
            >
                Términos de servicio
            </a>{' '}
            de Google.
        </p>
    );
}
