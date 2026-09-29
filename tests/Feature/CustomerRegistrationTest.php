<?php

use App\Contracts\FirebaseIdTokenVerifier;
use App\Enums\CoverageScopeType;
use App\Models\CoverageZone;
use App\Models\User;
use App\Services\Auth\VerifiedFirebasePhone;
use Illuminate\Support\Facades\Notification;

function customerRegistrationPayload(array $overrides = []): array
{
    return [
        'first_name' => 'Ana',
        'last_name' => 'López',
        'phone_dial_code' => '+52',
        'phone_national' => '9611234567',
        'password' => 'Clave123!',
        'password_confirmation' => 'Clave123!',
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

function fakeRegistrationPhone(string $phone): void
{
    app()->instance(FirebaseIdTokenVerifier::class, new class($phone) implements FirebaseIdTokenVerifier
    {
        public function __construct(private string $phone) {}

        public function verify(string $idToken): VerifiedFirebasePhone
        {
            return new VerifiedFirebasePhone($this->phone, 'firebase-uid');
        }
    });
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
    $this->get(route('register.verify-phone'))
        ->assertRedirect(route('register'));
});

test('a guest can register without an email and must verify the phone', function () {
    Notification::fake();

    $this->post(route('register.store'), customerRegistrationPayload())
        ->assertRedirect(route('register.verify-phone'));

    $this->assertGuest();

    $user = User::query()->where('phone', '+529611234567')->first();

    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Ana')
        ->and($user->last_name)->toBe('López')
        ->and($user->email)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone_verified_at)->toBeNull()
        ->and($user->customer)->not->toBeNull();

    $address = $user->customer->addresses()->first();

    expect($address)->not->toBeNull()
        ->and($address->is_default)->toBeTrue()
        ->and($address->address_text)->toBe('Calle Central 12, Comitán');

    Notification::assertNothingSent();
});

test('registration rejects weak passwords', function () {
    $this->post(route('register.store'), customerRegistrationPayload([
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->assertSessionHasErrors(['password']);
});

test('registration rejects a country code other than mexico', function () {
    $this->post(route('register.store'), customerRegistrationPayload([
        'phone_dial_code' => '+502',
        'phone_national' => '55551234',
    ]))->assertSessionHasErrors(['phone_dial_code']);
});

test('registration requires a phone with the country length', function () {
    $this->post(route('register.store'), customerRegistrationPayload([
        'phone_national' => '123',
    ]))->assertSessionHasErrors(['phone_national']);
});

test('registration rejects a phone that already belongs to an account', function () {
    User::factory()->customer()->create(['phone' => '+529611234567']);

    $this->post(route('register.store'), customerRegistrationPayload())
        ->assertSessionHasErrors(['phone']);
});

test('an unfinished registration can be continued with the same phone', function () {
    $this->post(route('register.store'), customerRegistrationPayload());

    $this->post(route('register.store'), customerRegistrationPayload([
        'first_name' => 'Anita',
    ]))->assertRedirect(route('register.verify-phone'));

    expect(User::query()->where('phone', '+529611234567')->count())->toBe(1)
        ->and(User::query()->where('phone', '+529611234567')->value('first_name'))->toBe('Anita');
});

test('a customer can verify the phone and continue to the cart', function () {
    $this->post(route('register.store'), customerRegistrationPayload());
    fakeRegistrationPhone('+529611234567');

    $this->get(route('register.verify-phone'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/register/verify-phone')
            ->where('phone', '+529611234567'));

    $this->post(route('register.verify-phone.store'), [
        'firebase_id_token' => 'valid-token',
    ])->assertRedirect(route('cart'));

    $user = User::query()->where('phone', '+529611234567')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->phone_verified_at)->not->toBeNull()
        ->and($user->email)->toBeNull();
});

test('registration from custom order continues there after the phone is verified', function () {
    $this->get(route('register', ['continue' => 'custom-order']))
        ->assertOk()
        ->assertSessionHas('register.continue', route('customer.custom-orders.create'));

    $this->post(route('register.store'), customerRegistrationPayload([
        'phone_national' => '9617654321',
    ]))->assertRedirect(route('register.verify-phone'));

    fakeRegistrationPhone('+529617654321');

    $this->post(route('register.verify-phone.store'), [
        'firebase_id_token' => 'valid-token',
    ])->assertRedirect(route('customer.custom-orders.create'));
});

test('a firebase token for another phone does not activate the account', function () {
    $this->post(route('register.store'), customerRegistrationPayload());
    fakeRegistrationPhone('+529600000000');

    $this->post(route('register.verify-phone.store'), [
        'firebase_id_token' => 'valid-token',
    ])->assertSessionHasErrors(['firebase_id_token']);

    $this->assertGuest();
    expect(User::query()->where('phone', '+529611234567')->value('phone_verified_at'))->toBeNull();
});

test('registration rejects an address outside platform coverage', function () {
    CoverageZone::factory()->create([
        'scope_type' => CoverageScopeType::Platform,
        'center_latitude' => 16.2514,
        'center_longitude' => -92.1342,
        'radius_meters' => 2000,
        'is_active' => true,
    ]);

    $this->post(route('register.store'), customerRegistrationPayload([
        'latitude' => 16.40,
        'longitude' => -92.40,
        'address_text' => 'Fuera de zona',
    ]))->assertSessionHasErrors(['latitude']);

    expect(User::query()->where('phone', '+529611234567')->exists())->toBeFalse();
});

test('an existing customer can sign in with email or phone', function () {
    $user = User::factory()->customer()->create([
        'email' => 'ana.vieja@example.com',
        'phone' => '+529611234567',
        'email_verified_at' => now(),
        'phone_verified_at' => null,
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'email' => 'ana.vieja@example.com',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
    auth()->logout();

    $this->post(route('login.store'), [
        'email' => '9611234567',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('a new customer signs in with the verified phone', function () {
    $user = User::factory()->customer()->create([
        'email' => null,
        'phone' => '+529611234567',
        'email_verified_at' => null,
        'phone_verified_at' => now(),
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'email' => '+529611234567',
        'password' => 'password',
    ])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('a customer without a verified email or phone cannot sign in', function () {
    User::factory()->customer()->create([
        'email' => null,
        'phone' => '+529611234567',
        'email_verified_at' => null,
        'phone_verified_at' => null,
        'password' => 'password',
    ]);

    $this->post(route('login.store'), [
        'email' => '9611234567',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('checkout sends an unverified customer to confirm the phone', function () {
    $user = User::factory()->customer()->create([
        'phone_verified_at' => null,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('customer.checkout'))
        ->assertRedirect(route('customer.phone.confirm'));

    $this->actingAs($user)
        ->get(route('customer.phone.confirm'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('customer/phone/confirm'));
});
