import assert from 'node:assert/strict';
import test from 'node:test';
import { canSendPhoneCode, firebasePhoneErrorMessage, phoneResendLabel, tickCooldown } from './firebase-phone.ts';

test('blocks a second send while busy or cooling down', () => {
    assert.equal(canSendPhoneCode({ busy: true, cooldownSeconds: 0 }), false);
    assert.equal(canSendPhoneCode({ busy: false, cooldownSeconds: 12 }), false);
    assert.equal(canSendPhoneCode({ busy: false, cooldownSeconds: 0 }), true);
});

test('cooldown label and tick', () => {
    assert.equal(phoneResendLabel(60), 'Reenviar código en 60 s');
    assert.equal(phoneResendLabel(0), 'Reenviar código');
    assert.equal(tickCooldown(2), 1);
    assert.equal(tickCooldown(0), 0);
});

test('maps firebase phone errors to spanish', () => {
    assert.equal(
        firebasePhoneErrorMessage({ code: 'auth/invalid-phone-number' }),
        'El número de teléfono no es válido.',
    );
    assert.equal(
        firebasePhoneErrorMessage({ code: 'auth/invalid-verification-code' }),
        'El código ingresado no es correcto.',
    );
    assert.equal(
        firebasePhoneErrorMessage({ code: 'auth/code-expired' }),
        'El código ha expirado. Solicita uno nuevo.',
    );
    assert.equal(
        firebasePhoneErrorMessage({ code: 'auth/too-many-requests' }),
        'Se realizaron demasiados intentos. Espera un momento antes de volver a intentarlo.',
    );
    assert.equal(
        firebasePhoneErrorMessage({ code: 'auth/internal-error' }),
        'No se pudo completar la verificación. Inténtalo de nuevo.',
    );
});
