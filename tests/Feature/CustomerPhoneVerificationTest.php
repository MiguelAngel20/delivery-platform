<?php

use App\Contracts\FirebaseIdTokenVerifier;
use App\Models\User;
use App\Services\Auth\FirebaseIdTokenException;
use App\Services\Auth\VerifiedFirebasePhone;
use Illuminate\Support\Facades\RateLimiter;

function fakePhoneVerifier(string $phone): void
{
    app()->instance(FirebaseIdTokenVerifier::class, new class($phone) implements FirebaseIdTokenVerifier
    {
        public function __construct(private string $phone) {}

        public function verify(string $idToken): VerifiedFirebasePhone
        {
            return match ($idToken) {
                'expired' => throw new FirebaseIdTokenException('expired'),
                'invalid' => throw new FirebaseIdTokenException('invalid'),
                default => new VerifiedFirebasePhone($this->phone, 'firebase-uid'),
            };
        }
    });
}

test('guests cannot verify a phone', function () {
    $this->post(route('customer.phone.verification.store'), [
        'firebase_id_token' => 'token',
    ])->assertRedirect(route('login'));
});

test('a customer confirms the phone when the firebase token matches', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => null,
    ]);
    fakePhoneVerifier('+529633133731');

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => 'valid-token',
            'user_id' => 999,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('a firebase token for another phone is rejected', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => null,
    ]);
    fakePhoneVerifier('+529633133732');

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => 'valid-token',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('firebase_id_token');

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('invalid and expired firebase tokens are rejected', function (string $token) {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => null,
    ]);
    fakePhoneVerifier('+529633133731');

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => $token,
        ])
        ->assertSessionHasErrors('firebase_id_token');

    expect($user->fresh()->phone_verified_at)->toBeNull();
})->with(['invalid', 'expired']);

test('a customer cannot verify another account by sending its id', function () {
    $actor = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => null,
    ]);
    $other = User::factory()->customer()->create([
        'phone' => '+529633133732',
        'phone_verified_at' => null,
    ]);
    fakePhoneVerifier('+529633133731');

    $this->actingAs($actor)
        ->from(route('customer.profile.index'))
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => 'valid-token',
            'user_id' => $other->id,
        ])
        ->assertRedirect();

    expect($actor->fresh()->phone_verified_at)->not->toBeNull()
        ->and($other->fresh()->phone_verified_at)->toBeNull();
});

test('drivers cannot verify a customer phone', function () {
    $driver = User::factory()->driver()->create([
        'phone' => '+529633133731',
    ]);

    $this->actingAs($driver)
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => 'valid-token',
        ])
        ->assertForbidden();
});

test('a customer cannot save a phone outside the mexico dial code', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->patch(route('customer.phone.update'), [
            'phone_dial_code' => '+502',
            'phone_national' => '55551234',
        ])
        ->assertSessionHasErrors(['phone_dial_code']);

    expect($user->fresh()->phone)->toBe('+529633133731')
        ->and($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('changing the phone clears phone_verified_at', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->patch(route('customer.phone.update'), [
            'phone_dial_code' => '+52',
            'phone_national' => '9633133732',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $fresh = $user->fresh();

    expect($fresh->phone)->toBe('+529633133732')
        ->and($fresh->phone_verified_at)->toBeNull();
});

test('saving the same phone keeps the verification', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->patch(route('customer.phone.update'), [
            'phone_dial_code' => '+52',
            'phone_national' => '9633133731',
        ])
        ->assertRedirect();

    expect($user->fresh()->phone_verified_at)->not->toBeNull();
});

test('a direct phone change also clears verification', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => now(),
    ]);

    $user->update(['phone' => '+529633133740']);

    expect($user->fresh()->phone_verified_at)->toBeNull();
});

test('phone verification attempts are rate limited', function () {
    $user = User::factory()->customer()->create([
        'phone' => '+529633133731',
        'phone_verified_at' => null,
    ]);
    fakePhoneVerifier('+529633133731');
    RateLimiter::clear(md5('customer-phone-verificationphone-verify:'.$user->id));

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->actingAs($user)
            ->from(route('customer.profile.index'))
            ->post(route('customer.phone.verification.store'), [
                'firebase_id_token' => 'valid-token',
            ])
            ->assertSessionHasNoErrors();
    }

    $this->actingAs($user)
        ->from(route('customer.profile.index'))
        ->post(route('customer.phone.verification.store'), [
            'firebase_id_token' => 'valid-token',
        ])
        ->assertSessionHasErrors('firebase_id_token');
});
