<?php

use App\Models\User;
use App\Notifications\Auth\CustomerEmailVerificationCode;
use Illuminate\Support\Facades\Notification;

function customerRegistrationPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ana',
        'last_name' => 'López',
        'email' => 'ana.lopez@example.com',
        'phone_dial_code' => '+52',
        'phone_national' => '9611234567',
        'password' => 'password',
        'password_confirmation' => 'password',
        'address_label' => 'Casa',
        'address_text' => 'Calle Central 12, Comitán',
        'formatted_address' => 'Calle Central 12, Comitán de Domínguez, Chiapas',
        'reference' => 'Casa azul',
        'latitude' => 16.2512,
        'longitude' => -92.1342,
        'place_id' => 'ChIJtest',
        'google_maps_url' => null,
        ...$overrides,
    ];
}

test('registration screen can be rendered', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/register/index')
            ->has('dialCodes.0.dial')
            ->has('dialCodes.0.national_length')
            ->where('defaultDialCode', '+52'));
});

test('verification screen redirects guests without a pending registration', function () {
    $this->get(route('register.verify-email'))
        ->assertRedirect(route('register'));
});

test('a guest can register and must verify email before checkout', function () {
    Notification::fake();

    $this->post(route('register.store'), customerRegistrationPayload())
        ->assertRedirect(route('register.verify-email'));

    $this->assertGuest();

    $user = User::query()->where('email', 'ana.lopez@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Ana')
        ->and($user->last_name)->toBe('López')
        ->and($user->phone)->toBe('+529611234567')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone_verified_at)->toBeNull()
        ->and($user->customer)->not->toBeNull();

    $address = $user->customer->addresses()->first();

    expect($address)->not->toBeNull()
        ->and($address->is_default)->toBeTrue()
        ->and($address->address_text)->toBe('Calle Central 12, Comitán');

    Notification::assertSentTo($user, CustomerEmailVerificationCode::class);
});

test('registration requires a valid email and matching country phone length', function () {
    $this->post(route('register.store'), customerRegistrationPayload([
        'email' => 'no-es-correo',
        'phone_national' => '123',
    ]))->assertSessionHasErrors(['email', 'phone_national']);
});

test('registration rejects an email that already exists', function () {
    User::factory()->create(['email' => 'ana.lopez@example.com']);

    $this->post(route('register.store'), customerRegistrationPayload())
        ->assertSessionHasErrors(['email']);
});

test('a customer can verify the email code and continue to checkout', function () {
    Notification::fake();

    $this->post(route('register.store'), customerRegistrationPayload());

    $user = User::query()->where('email', 'ana.lopez@example.com')->firstOrFail();
    $code = '';

    Notification::assertSentTo(
        $user,
        CustomerEmailVerificationCode::class,
        function (CustomerEmailVerificationCode $notification) use (&$code): bool {
            $code = $notification->code;

            return true;
        },
    );

    $this->get(route('register.verify-email'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/register/verify-email')
            ->where('email', 'ana.lopez@example.com'));

    $this->post(route('register.verify-email.store'), ['code' => $code])
        ->assertRedirect(route('customer.checkout'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('an invalid verification code is rejected', function () {
    Notification::fake();

    $this->post(route('register.store'), customerRegistrationPayload());

    $this->post(route('register.verify-email.store'), ['code' => '000000'])
        ->assertSessionHasErrors(['code']);

    $this->assertGuest();
});

test('the verification code can be resent', function () {
    Notification::fake();

    $this->post(route('register.store'), customerRegistrationPayload());

    $user = User::query()->where('email', 'ana.lopez@example.com')->firstOrFail();

    $this->post(route('register.verify-email.resend'))
        ->assertRedirect();

    Notification::assertSentToTimes($user, CustomerEmailVerificationCode::class, 2);
});
