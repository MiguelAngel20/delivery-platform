<?php

use App\Services\Auth\FirebaseIdTokenException;
use App\Services\Auth\GoogleFirebaseIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array{private: string, certificate: string}
 */
function firebaseTestKeyPair(): array
{
    $options = [
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    $opensslConfig = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

    if (is_file($opensslConfig)) {
        $options['config'] = $opensslConfig;
    }

    $key = openssl_pkey_new($options);

    if ($key === false) {
        throw new RuntimeException(openssl_error_string() ?: 'OpenSSL no pudo crear la llave de prueba.');
    }

    openssl_pkey_export($key, $private, null, $options);
    $details = openssl_pkey_get_details($key);

    return [
        'private' => $private,
        'certificate' => $details['key'],
    ];
}

function firebaseTestToken(string $privateKey, array $claims = []): string
{
    return JWT::encode(array_merge([
        'iss' => 'https://securetoken.google.com/chisdrive-test',
        'aud' => 'chisdrive-test',
        'sub' => 'firebase-uid',
        'iat' => time(),
        'exp' => time() + 3600,
        'phone_number' => '+529633133731',
        'firebase' => [
            'sign_in_provider' => 'phone',
        ],
    ], $claims), $privateKey, 'RS256', 'test-key');
}

beforeEach(function () {
    config([
        'push.fcm.project_id' => 'chisdrive-test',
        'push.web.project_id' => 'chisdrive-test',
    ]);
    Cache::flush();
});

test('a signed firebase phone token is accepted', function () {
    $keys = firebaseTestKeyPair();
    Http::fake([
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response([
            'test-key' => $keys['certificate'],
        ]),
    ]);

    $verified = app(GoogleFirebaseIdTokenVerifier::class)->verify(
        firebaseTestToken($keys['private']),
    );

    expect($verified->phone)->toBe('+529633133731')
        ->and($verified->uid)->toBe('firebase-uid');
});

test('an expired firebase token is rejected', function () {
    $keys = firebaseTestKeyPair();
    Http::fake([
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response([
            'test-key' => $keys['certificate'],
        ]),
    ]);

    expect(fn () => app(GoogleFirebaseIdTokenVerifier::class)->verify(
        firebaseTestToken($keys['private'], ['exp' => time() - 120]),
    ))->toThrow(FirebaseIdTokenException::class, 'expired');
});

test('a token signed with another key is rejected', function () {
    $trusted = firebaseTestKeyPair();
    $untrusted = firebaseTestKeyPair();
    Http::fake([
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response([
            'test-key' => $trusted['certificate'],
        ]),
    ]);

    expect(fn () => app(GoogleFirebaseIdTokenVerifier::class)->verify(
        firebaseTestToken($untrusted['private']),
    ))->toThrow(FirebaseIdTokenException::class, 'invalid');
});

test('a token for another firebase project is rejected', function () {
    $keys = firebaseTestKeyPair();
    Http::fake([
        'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com' => Http::response([
            'test-key' => $keys['certificate'],
        ]),
    ]);

    expect(fn () => app(GoogleFirebaseIdTokenVerifier::class)->verify(
        firebaseTestToken($keys['private'], [
            'aud' => 'other-project',
            'iss' => 'https://securetoken.google.com/other-project',
        ]),
    ))->toThrow(FirebaseIdTokenException::class, 'invalid');
});
